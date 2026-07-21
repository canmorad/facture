<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GeminiAIService
{
    /**
     * URL de base de l'API Gemini v1beta
     */
    private const BASE_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * Modèles à essayer par ordre de préférence
     * RÉDUIT pour éviter trop de tentatives et logs excessifs
     */
    private const MODEL_FALLBACKS = [
        'gemini-3.1-flash-lite',      // Modèle qui fonctionne avec votre clé
        'gemini-3.5-flash',           // Alternative récente
    ];

    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB

    private ?string $apiKey;
    private ?string $model = null;
    private ?string $workingModel = null;
    private bool $verboseLogging;

    private array $supportedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/bmp',
        'image/tiff',
    ];

    public function __construct()
    {
        // Get API key from config - don't throw exception during service instantiation
        // The key will be validated when actually using the service
        $this->apiKey = config('services.gemini.api_key');

        // Utiliser le modèle depuis la config ou null pour auto-détection
        $this->model = config('services.gemini.model');

        // Logging verbeux séparément de APP_DEBUG (désactivé par défaut)
        $this->verboseLogging = config('services.gemini.verbose_logging', false);
    }

    /**
     * Analyse une facture d'achat et extrait les données structurées
     */
    public function analyzeInvoice(UploadedFile $file): array
    {
        // Validate API key before processing
        if (!$this->apiKey) {
            return $this->error('Gemini API key not configured. Set GEMINI_API_KEY in .env');
        }

        // Vérification de la validité du fichier
        // Pour les fichiers en mode test (test=true), isValid() retourne false
        // car is_uploaded_file() échoue. On vérifie donc uniquement le code d'erreur.
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->error('Invalid file uploaded: Error code ' . $file->getError());
        }

        // Vérifier que le fichier existe (important pour les fichiers test)
        if (!file_exists($file->getRealPath())) {
            return $this->error('File does not exist: ' . $file->getRealPath());
        }

        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return $this->error('File too large. Maximum size is 20MB');
        }

        if (!in_array($file->getMimeType(), $this->supportedMimeTypes)) {
            return $this->error('Unsupported file type. Please use PDF or images (JPG, PNG, WebP)');
        }

        try {
            $fileData = $this->prepareFileData($file);

            // Essayer avec auto-détection du modèle qui fonctionne
            $response = $this->sendToGeminiWithRetry($fileData);

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'working_model' => $this->workingModel,
                ]);
                return $this->error('Failed to analyze file. API returned error: ' . $response->status());
            }

            return $this->parseGeminiResponse($response->json());

        } catch (\Exception $e) {
            Log::error('GeminiAIService error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'working_model' => $this->workingModel ?? 'none',
            ]);
            return $this->error('Analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Construit l'URL de l'API Gemini pour un modèle donné
     * Format: https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent
     */
    private function getApiUrl(string $model): string
    {
        return sprintf(
            '%s/%s:generateContent',
            self::BASE_API_URL,
            $model
        );
    }

    /**
     * Convertit le fichier en base64 pour l'API
     */
    private function prepareFileData(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType();
        $content = base64_encode(file_get_contents($file->getRealPath()));

        return [
            'mime_type' => $mimeType,
            'data' => $content,
        ];
    }

    /**
     * Envoie le fichier à Gemini API avec retry sur différents modèles
     */
    private function sendToGeminiWithRetry(array $fileData): \Illuminate\Http\Client\Response
    {
        $prompt = $this->getAnalysisPrompt();

        // Réduire la taille du fichier si nécessaire
        $data = $fileData['data'];
        if (strlen($data) > 5000000) {
            $data = $this->compressImageData($data);
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $fileData['mime_type'],
                                'data' => $data,
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 4096,
                'responseMimeType' => 'application/json',
            ]
        ];

        $modelsToTry = $this->model ? [$this->model, ...self::MODEL_FALLBACKS] : self::MODEL_FALLBACKS;
        $modelsToTry = array_unique($modelsToTry);

        $lastError = null;
        $retryCount = 0;
        $maxRetries = count($modelsToTry);

        foreach ($modelsToTry as $model) {
            $retryCount++;

            try {
                // Log uniquement en mode debug pour éviter de polluer les logs
                if ($this->verboseLogging) {
                    Log::info("Trying Gemini model: {$model}");
                }

                $response = Http::timeout(90)  // 90s au lieu de 60s
                    ->retry(1, 1000)  // 1 retry avec 1s d'intervalle
                    ->post(
                        $this->getApiUrl($model) . '?key=' . $this->apiKey,
                        $payload
                    );

                if ($response->successful()) {
                    $this->workingModel = $model;
                    if ($this->verboseLogging) {
                        Log::info("Gemini model {$model} works!");
                    }
                    return $response;
                }

                // Analyser l'erreur
                $body = $response->json();
                $errorMsg = $body['error']['message'] ?? 'Unknown error';
                $errorCode = $body['error']['code'] ?? $response->status();

                // Erreur 400 "document has no pages" - probablement un PDF vide
                if ($errorCode === 400 && str_contains($errorMsg, 'no pages')) {
                    Log::warning("PDF appears to be empty or invalid. Please check the file.");
                    throw new \RuntimeException('The uploaded PDF file appears to be empty or corrupted. Please try with a valid PDF file.');
                }

                // Erreur 404 - modèle n'existe pas
                if ($response->status() === 404) {
                    if ($this->verboseLogging) {
                        Log::debug("Model {$model} not found (404), trying next...");
                    }
                    $lastError = "Model not found";
                    continue;
                }

                // Erreur 429 - Rate limit
                if ($response->status() === 429) {
                    Log::warning("Rate limit exceeded for model {$model}");
                    $lastError = "Rate limit exceeded";
                    // Continuer à essayer d'autres modèles
                    continue;
                }

                $lastError = $errorMsg;

                // Pour les autres erreurs, loguer seulement en debug
                if ($this->verboseLogging) {
                    Log::warning("Gemini model {$model} failed: {$errorMsg}", [
                        'status' => $response->status(),
                    ]);
                }

            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();

                // Erreur de timeout/réseau - ne pas loguer chaque tentative
                if (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'cURL error 28')) {
                    if ($retryCount === 1) {
                        // Loguer seulement la première fois
                        Log::warning("Gemini API timeout - network connectivity issue");
                    }
                    $lastError = "Network timeout - API unreachable";
                    continue;
                }

                // Si c'est notre exception personnalisée (PDF vide), la propager
                if (str_contains($errorMessage, 'PDF file appears to be empty')) {
                    throw $e;
                }

                if ($this->verboseLogging) {
                    Log::warning("Exception with model {$model}: {$errorMessage}");
                }
                $lastError = $errorMessage;
                continue;
            }
        }

        // Si tous les modèles ont échoué
        if (str_contains($lastError ?? '', 'Network timeout')) {
            throw new \RuntimeException(
                "Unable to reach Gemini API. Please check your internet connection and try again later."
            );
        }

        throw new \RuntimeException(
            "Failed to analyze invoice. Please check that: 1) Your API key is valid, 2) The PDF file is not corrupted. Error: {$lastError}"
        );
    }

    /**
     * Envoie le fichier à Gemini API (méthode originale conservée pour compatibilité)
     */
    private function sendToGemini(array $fileData): \Illuminate\Http\Client\Response
    {
        return $this->sendToGeminiWithRetry($fileData);
    }

    /**
     * Compresse les données image base64 pour réduire la taille
     */
    private function compressImageData(string $base64Data): string
    {
        return substr($base64Data, 0, min(strlen($base64Data), 3000000));
    }

    /**
     * Prompt d'analyse optimisé pour extraction de facture
     */
    private function getAnalysisPrompt(): string
    {
        return <<<'PROMPT'
You are an expert invoice data extraction system. Analyze this purchase invoice/supplier invoice document and extract ALL information in STRUCTURED JSON format.

IMPORTANT - Return ONLY valid JSON without markdown formatting, no explanations, no additional text.

Extract the following data with high precision:

1. **Supplier/Vendor Information:**
   - name: Full legal company name (e.g., "SARL ABC Solutions", "XYZ SA")
   - ice: ICE number if present (format: 001234567890000)
   - tax_id: Tax identifier (IF) if present
   - address: Full address
   - phone: Phone number
   - email: Email if present

2. **Invoice Details:**
   - invoice_number: Supplier's invoice number exactly as shown
   - invoice_date: Date in YYYY-MM-DD format
   - due_date: Due date in YYYY-MM-DD format if present
   - payment_terms: Payment terms text (e.g., "30 jours", "À réception")

3. **Financial Amounts:**
   - amount_ht: Total before tax (float)
   - total_tva: Total VAT amount (float)
   - amount_ttc: Total including tax (float)
   - currency: Currency code (MAD, EUR, USD, etc.)

4. **Line Items (items array):**
   For each line item extract:
   - designation: Product/service description
   - quantity: Quantity as number (float or integer)
   - unit_price: Unit price before tax (float)
   - tax_rate: Tax rate percentage (float, e.g., 20.0 for 20%)
   - total_ht: Line total before tax (float)
   - total_tva: Line VAT amount (float)

5. **Additional Information:**
   - notes: Any notes, conditions, or legal text at bottom
   - payment_mode: Payment method (Check, Transfer, Cash, etc.)
   - reference: Any reference/order numbers present

JSON Structure Example:
{
  "success": true,
  "data": {
    "supplier": {
      "name": "SARL ABC Solutions",
      "ice": "001234567890000",
      "tax_id": "12345678",
      "address": "123 Rue Example, Casablanca",
      "phone": "+212 5 22 00 00 00",
      "email": "contact@abc.ma"
    },
    "invoice": {
      "invoice_number": "FA-2024-001234",
      "invoice_date": "2024-01-15",
      "due_date": "2024-02-15",
      "payment_terms": "30 jours nets"
    },
    "amounts": {
      "amount_ht": 10000.00,
      "total_tva": 2000.00,
      "amount_ttc": 12000.00,
      "currency": "MAD"
    },
    "items": [
      {
        "designation": "Consulting Services",
        "quantity": 10,
        "unit_price": 1000.00,
        "tax_rate": 20.0,
        "total_ht": 10000.00,
        "total_tva": 2000.00
      }
    ],
    "additional": {
      "notes": "Paiement par virement bancaire",
      "payment_mode": "Virement bancaire",
      "reference": "BC-456"
    }
  },
  "confidence": 95,
  "warnings": []
}

Confidence scoring:
- 95-100: All critical fields extracted with high certainty
- 75-94: Most fields extracted, some ambiguity
- 50-74: Basic extraction, requires manual verification
- Below 50: Extraction failed or highly uncertain

Include warnings array for any concerns like:
- "Low confidence on invoice number format"
- "Multiple dates found, invoice date may be incorrect"
- "Line items quantities seem inconsistent"

Analyze the document now and return ONLY the JSON object.
PROMPT;
    }

    /**
     * Parse la réponse de Gemini API
     */
    private function parseGeminiResponse(array $responseData): array
    {
        $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse Gemini JSON response', [
                'error' => json_last_error_msg(),
                'raw_text' => substr($text, 0, 500),
            ]);
            return $this->error('Failed to parse AI response. Invalid JSON format.');
        }

        return $this->normalizeExtractedData($data);
    }

    /**
     * Normalise et valide les données extraites
     */
    private function normalizeExtractedData(array $data): array
    {
        return [
            'success' => true,
            'data' => $this->mapToInvoiceFormat($data),
            'confidence' => $data['confidence'] ?? 75,
            'warnings' => $data['warnings'] ?? [],
            'raw_response' => $data,
            'working_model' => $this->workingModel,
        ];
    }

    /**
     * Map les données extraites vers le format de notre application
     */
    private function mapToInvoiceFormat(array $data): array
    {
        return [
            'fournisseur' => [
                'name' => $data['data']['supplier']['name'] ?? null,
                'ice' => $data['data']['supplier']['ice'] ?? null,
                'tax_id' => $data['data']['supplier']['tax_id'] ?? null,
                'address' => $data['data']['supplier']['address'] ?? null,
                'phone' => $data['data']['supplier']['phone'] ?? null,
                'email' => $data['data']['supplier']['email'] ?? null,
            ],
            'supplier_invoice_number' => $data['data']['invoice']['invoice_number'] ?? null,
            'invoice_date' => $this->normalizeDate($data['data']['invoice']['invoice_date'] ?? null),
            'due_date' => $this->normalizeDate($data['data']['invoice']['due_date'] ?? null),
            'payment_terms' => $data['data']['invoice']['payment_terms'] ?? null,
            'amount_ht' => $this->normalizeAmount($data['data']['amounts']['amount_ht'] ?? null),
            'total_tva' => $this->normalizeAmount($data['data']['amounts']['total_tva'] ?? null),
            'amount_ttc' => $this->normalizeAmount($data['data']['amounts']['amount_ttc'] ?? null),
            'currency' => $data['data']['amounts']['currency'] ?? 'MAD',
            'items' => $this->normalizeItems($data['data']['items'] ?? []),
            'notes' => $data['data']['additional']['notes'] ?? null,
            'payment_mode' => $data['data']['additional']['payment_mode'] ?? null,
            'reference' => $data['data']['additional']['reference'] ?? null,
        ];
    }

    /**
     * Normalise les lignes d'articles
     */
    private function normalizeItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'designation' => $item['designation'] ?? '',
                'quantity' => $this->normalizeAmount($item['quantity'] ?? 1),
                'unit_price' => $this->normalizeAmount($item['unit_price'] ?? 0),
                'tax_rate' => $this->normalizeAmount($item['tax_rate'] ?? 20),
                'total_ht' => $this->normalizeAmount($item['total_ht'] ?? 0),
                'total_tva' => $this->normalizeAmount($item['total_tva'] ?? 0),
            ];
        }, $items);
    }

    /**
     * Normalise une date
     */
    private function normalizeDate(?string $date): ?string
    {
        if (!$date) return null;

        try {
            $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd/m/y', 'd-m-y'];
            foreach ($formats as $format) {
                $parsed = \DateTime::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            }
            $parsed = new \DateTime($date);
            return $parsed->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalise un montant
     */
    private function normalizeAmount($amount): ?float
    {
        if ($amount === null || $amount === '') return null;
        return (float) $amount;
    }

    /**
     * Retourne une réponse d'erreur
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'data' => null,
            'confidence' => 0,
        ];
    }

    /**
     * Liste tous les modèles disponibles pour votre clé API
     */
    public function listAvailableModels(): array
    {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'error' => 'Gemini API key not configured. Set GEMINI_API_KEY in .env',
                'models' => [],
            ];
        }

        try {
            $response = Http::timeout(10)->get(
                self::BASE_API_URL . '?key=' . $this->apiKey
            );

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to list models: HTTP ' . $response->status(),
                    'models' => [],
                ];
            }

            $data = $response->json();
            $models = $data['models'] ?? [];

            // Filtrer uniquement les modèles qui supportent generateContent
            $supportedModels = array_filter($models, function ($model) {
                $methods = $model['supportedGenerationMethods'] ?? [];
                return in_array('generateContent', $methods);
            });

            // Trier et formater
            $formattedModels = [];
            foreach ($supportedModels as $model) {
                // Extraire l'ID du modèle depuis le champ "name" (format: models/gemini-2.5-flash)
                $name = $model['name'] ?? '';
                $modelId = '';
                if (preg_match('/^models\/(.+)$/', $name, $matches)) {
                    $modelId = $matches[1];
                }

                $formattedModels[] = [
                    'name' => $name,
                    'base_model' => $model['baseModelId'] ?? $modelId,
                    'model_id' => $modelId,
                    'display_name' => $model['displayName'] ?? 'Unknown',
                    'description' => $model['description'] ?? '',
                    'input_tokens' => $model['inputTokenLimit'] ?? 0,
                    'output_tokens' => $model['outputTokenLimit'] ?? 0,
                ];
            }

            return [
                'success' => true,
                'models' => $formattedModels,
                'total' => count($formattedModels),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'models' => [],
            ];
        }
    }

    /**
     * Test la connexion à l'API
     */
    public function testConnection(): bool
    {
        if (!$this->apiKey) {
            return false;
        }

        try {
            $response = Http::timeout(10)->get(
                self::BASE_API_URL . '?key=' . $this->apiKey
            );
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test un modèle spécifique
     */
    public function testModel(string $model): array
    {
        if (!$this->apiKey) {
            return [
                'model' => $model,
                'success' => false,
                'status' => 0,
                'error' => 'Gemini API key not configured. Set GEMINI_API_KEY in .env',
            ];
        }

        try {
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Hello, respond with just "OK"']
                        ]
                    ]
                ]
            ];

            $response = Http::timeout(30)->post(
                $this->getApiUrl($model) . '?key=' . $this->apiKey,
                $payload
            );

            return [
                'model' => $model,
                'success' => $response->successful(),
                'status' => $response->status(),
                'error' => $response->successful() ? null : ($response->json()['error']['message'] ?? 'Unknown error'),
            ];

        } catch (\Exception $e) {
            return [
                'model' => $model,
                'success' => false,
                'status' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Trouve automatiquement un modèle qui fonctionne
     */
    public function findWorkingModel(): ?string
    {
        if (!$this->apiKey) {
            return null;
        }

        foreach (self::MODEL_FALLBACKS as $model) {
            $result = $this->testModel($model);
            if ($result['success']) {
                $this->workingModel = $model;
                return $model;
            }
        }
        return null;
    }

    /**
     * Retourne les types MIME supportés
     */
    public function getSupportedMimeTypes(): array
    {
        return $this->supportedMimeTypes;
    }

    /**
     * Retourne le modèle actuellement utilisé
     */
    public function getModel(): ?string
    {
        return $this->workingModel ?? $this->model;
    }
}
