<?php

namespace App\Console\Commands;

use App\Services\GeminiAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Output\OutputInterface;

#[\Illuminate\Console\Attributes\Signature('purchase-invoice:test-gemini {file : Path to invoice file (PDF or image)} {--c|check : Only check API connection}')]
#[\Illuminate\Console\Attributes\Description('Test Gemini API connection and invoice analysis')]
class TestGeminiInvoiceAnalysis extends Command
{
    private GeminiAIService $aiService;

    public function __construct(GeminiAIService $aiService)
    {
        parent::__construct();
        $this->aiService = $aiService;
    }

    public function handle(): int
    {
        $this->info("\n" . str_repeat('═', 70));
        $this->info('  🤖 Gemini API - Purchase Invoice Analysis Test');
        $this->info(str_repeat('═', 70) . "\n");

        // Mode check only
        if ($this->option('check')) {
            return $this->checkConnection();
        }

        // Mode analyse
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("❌ File not found: {$filePath}");
            $this->warn('Usage: php artisan purchase-invoice:test-gemini /path/to/invoice.pdf');
            return self::FAILURE;
        }

        return $this->analyzeInvoice($filePath);
    }

    /**
     * Test la connexion à l'API Gemini
     */
    private function checkConnection(): int
    {
        $this->info('🔍 Checking Gemini API connection...');

        try {
            $apiKey = config('services.gemini.api_key');

            if (!$apiKey || $apiKey === 'your_gemini_api_key_here') {
                $this->error('❌ Gemini API key not configured!');
                $this->warn('Please set GEMINI_API_KEY in your .env file');
                $this->warn('Get your free API key at: https://makersuite.google.com/app/apikey');
                return self::FAILURE;
            }

            $isConnected = $this->aiService->testConnection();

            if ($isConnected) {
                $this->info('✅ API connection successful!');
                $this->info('📡 Endpoint: https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash');
                return self::SUCCESS;
            } else {
                $this->error('❌ API connection failed!');
                $this->warn('Please check your API key and internet connection');
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Connection test failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Analyse une facture
     */
    private function analyzeInvoice(string $filePath): int
    {
        $this->info("📄 Analyzing file: " . basename($filePath));
        $this->info('⏳ Sending to Gemini API (this may take 10-30 seconds)...');

        try {
            // Créer un UploadedFile depuis le chemin
            // Constructor: __construct(string $path, string $originalName, string $mimeType = null, int $error = null, bool $test = false)
            $file = new \Illuminate\Http\UploadedFile(
                $filePath,                      // $path
                basename($filePath),            // $originalName
                mime_content_type($filePath),   // $mimeType
                UPLOAD_ERR_OK,                  // $error (NOT filesize!)
                true                            // $test mode
            );

            $startTime = microtime(true);
            $result = $this->aiService->analyzeInvoice($file);
            $duration = round(microtime(true) - $startTime, 2);

            // Affichage des résultats
            $this->newLine();
            $this->info(str_repeat('─', 70));
            $this->info("  📊 ANALYSIS RESULT ({$duration}s)");
            $this->info(str_repeat('─', 70) . "\n");

            if (!$result['success']) {
                $this->error('❌ Analysis failed: ' . ($result['error'] ?? 'Unknown error'));
                return self::FAILURE;
            }

            // Afficher le score de confiance
            $confidence = $result['confidence'] ?? 0;
            $confidenceColor = $confidence >= 90 ? 'green' : ($confidence >= 70 ? 'yellow' : 'red');
            $this->line("  📈 Confidence Score: <fg={$confidenceColor}>{$confidence}%</>");

            // Afficher les avertissements
            if (!empty($result['warnings'])) {
                $this->warn("\n  ⚠️  Warnings:");
                foreach ($result['warnings'] as $warning) {
                    $this->line("     - {$warning}");
                }
            }

            // Afficher les données extraites
            $this->displayExtractedData($result['data'] ?? []);

            // Afficher le JSON brut si verbose
            if ($this->getOutput()->isVerbose()) {
                $this->newLine();
                $this->info(str_repeat('─', 70));
                $this->info("  📄 RAW JSON RESPONSE");
                $this->info(str_repeat('─', 70));
                $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            $this->newLine();
            $this->info(str_repeat('═', 70));
            $this->info('✅ Analysis completed successfully!');
            $this->info(str_repeat('═', 70) . "\n");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Analysis failed: ' . $e->getMessage());
            $this->newLine();
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Affiche les données extraites de manière formatée
     */
    private function displayExtractedData(array $data): void
    {
        $this->newLine();
        $this->info('  🏢 SUPPLIER / FOURNISSEUR');
        $this->info(str_repeat('─', 70));

        $supplier = $data['fournisseur'] ?? [];
        $this->displayField('Name', $supplier['name'] ?? 'N/A');
        $this->displayField('ICE', $supplier['ice'] ?? 'N/A');
        $this->displayField('Tax ID (IF)', $supplier['tax_id'] ?? 'N/A');
        $this->displayField('Address', $supplier['address'] ?? 'N/A');
        $this->displayField('Phone', $supplier['phone'] ?? 'N/A');
        $this->displayField('Email', $supplier['email'] ?? 'N/A');

        $this->newLine();
        $this->info('  📃 INVOICE DETAILS');
        $this->info(str_repeat('─', 70));

        $this->displayField('Invoice Number', $data['supplier_invoice_number'] ?? 'N/A');
        $this->displayField('Invoice Date', $data['invoice_date'] ?? 'N/A');
        $this->displayField('Due Date', $data['due_date'] ?? 'N/A');
        $this->displayField('Payment Terms', $data['payment_terms'] ?? 'N/A');

        $this->newLine();
        $this->info('  💰 AMOUNTS');
        $this->info(str_repeat('─', 70));

        $this->displayField('Amount HT', $this->formatAmount($data['amount_ht'] ?? null) . ' ' . ($data['currency'] ?? 'MAD'));
        $this->displayField('Total TVA', $this->formatAmount($data['total_tva'] ?? null) . ' ' . ($data['currency'] ?? 'MAD'));
        $this->displayField('Amount TTC', $this->formatAmount($data['amount_ttc'] ?? null) . ' ' . ($data['currency'] ?? 'MAD'));

        // Lignes d'articles
        $items = $data['items'] ?? [];
        if (!empty($items)) {
            $this->newLine();
            $this->info('  📦 LINE ITEMS (' . count($items) . ' items)');
            $this->info(str_repeat('─', 70));

            foreach ($items as $index => $item) {
                $this->newLine();
                $this->info("  Item #" . ($index + 1) . ":");
                $this->displayField('  Designation', $item['designation'] ?? 'N/A', '    ');
                $this->displayField('  Quantity', $item['quantity'] ?? 'N/A', '    ');
                $this->displayField('  Unit Price', $this->formatAmount($item['unit_price'] ?? null), '    ');
                $this->displayField('  Tax Rate', ($item['tax_rate'] ?? 0) . '%', '    ');
                $this->displayField('  Total HT', $this->formatAmount($item['total_ht'] ?? null), '    ');
                $this->displayField('  Total TVA', $this->formatAmount($item['total_tva'] ?? null), '    ');
            }
        }

        $this->newLine();
        $this->info('  📝 ADDITIONAL INFO');
        $this->info(str_repeat('─', 70));

        $this->displayField('Payment Mode', $data['payment_mode'] ?? 'N/A');
        $this->displayField('Reference', $data['reference'] ?? 'N/A');
        $this->displayField('Notes', $data['notes'] ?? 'N/A');
    }

    /**
     * Affiche un champ avec label
     */
    private function displayField(string $label, $value, string $indent = '  '): void
    {
        if ($value === null || $value === '' || $value === 'N/A') {
            $this->line("{$indent}<fg=gray>{$label}:</> <fg=gray>N/A</>");
        } else {
            $this->line("{$indent}<fg=cyan>{$label}:</> {$value}");
        }
    }

    /**
     * Formate un montant
     */
    private function formatAmount($amount): string
    {
        if ($amount === null) {
            return 'N/A';
        }
        return number_format((float) $amount, 2, ',', ' ');
    }
}
