# Gemini API - Integration pour Analyse de Factures d'Achat

## 📋 Vue d'ensemble

Cette intégration utilise **Google Gemini API (Flash 1.5)** pour analyser automatiquement les factures d'achat fournisseurs et extraire les données structurées.

### ✨ Avantages

- **✅ Gratuit** - Tier généreux avec Gemini Flash 1.5
- **✅ Rapide** - Analyse en 10-30 secondes
- **✅ Précis** - Extraction structurée avec score de confiance
- **✅ Léger** - Aucun modèle local à installer
- **✅ Scalable** - API cloud robuste

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Frontend (Vue 3)                          │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  Upload Facture (PDF/Image)                                │  │
│  └───────────────────────────────────────────────────────────┘  │
│                               │                                    │
└───────────────────────────────┼────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Backend (Laravel)                            │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  POST /api/purchase-invoices/analyze                        │  │
│  └───────────────────────────────────────────────────────────┘  │
│                               │                                    │
│                               ▼                                    │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  PurchaseInvoiceController::analyze()                       │  │
│  └───────────────────────────────────────────────────────────┘  │
│                               │                                    │
│                               ▼                                    │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  PurchaseInvoiceService::analyzeFile()                     │  │
│  └───────────────────────────────────────────────────────────┘  │
│                               │                                    │
│                               ▼                                    │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │  GeminiAIService::analyzeInvoice()                         │  │
│  │  • Convertit fichier en base64                             │  │
│  │  • Envoie à Gemini API avec prompt structuré               │  │
│  │  • Parse et normalise la réponse JSON                      │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Google Gemini API                             │
│  • Endpoint: /v1beta/models/gemini-1.5-flash:generateContent   │
│  • Analyse PDF/Images (JPEG, PNG, WebP, BMP, TIFF)              │
│  • Retourne JSON structuré avec données extraites               │
└─────────────────────────────────────────────────────────────────┘
```

## 📁 Fichiers Créés/Modifiés

### Nouveaux fichiers

| Fichier | Description |
|---------|-------------|
| `backend/app/Services/GeminiAIService.php` | Service principal d'analyse |
| `backend/app/Console/Commands/TestGeminiInvoiceAnalysis.php` | Commande de test |

### Fichiers modifiés

| Fichier | Modifications |
|---------|---------------|
| `backend/app/Http/Controllers/PurchaseInvoiceController.php` | Ajout méthode `analyze()` |
| `backend/app/Services/PurchaseInvoiceService.php` | Intégration `GeminiAIService` |
| `backend/routes/api.php` | Route `POST /purchase-invoices/analyze` |
| `backend/config/services.php` | Configuration Gemini |
| `backend/.env.example` | Variable `GEMINI_API_KEY` |
| `backend/composer.json` | Retrait dépendance Tesseract |

### Fichiers supprimés

| Fichier | Raison |
|---------|--------|
| `backend/app/Services/OcrExtractionService.php` | Remplacé par GeminiAIService |
| `backend/app/Http/Controllers/OcrController.php` | Plus nécessaire |

## ⚙️ Configuration

### 1. Clé API Gemini

Ajoutez dans votre `.env`:

```bash
# Get your free API key at: https://makersuite.google.com/app/apikey
GEMINI_API_KEY=votre_clé_api_ici
```

### 2. Obtenir une clé API

1. Allez sur https://makersuite.google.com/app/apikey
2. Connectez-vous avec votre compte Google
3. Cliquez sur "Create API Key"
4. Copiez la clé et collez-la dans votre `.env`

## 🚀 Utilisation

### Endpoint API

```http
POST /api/purchase-invoices/analyze
Content-Type: multipart/form-data

file: <fichier_pdf_ou_image>
```

### Réponse JSON

```json
{
  "success": true,
  "data": {
    "fournisseur": {
      "name": "SARL ABC Solutions",
      "ice": "001234567890000",
      "tax_id": "12345678",
      "address": "123 Rue Example, Casablanca",
      "phone": "+212 5 22 00 00 00",
      "email": "contact@abc.ma"
    },
    "supplier_invoice_number": "FA-2024-001234",
    "invoice_date": "2024-01-15",
    "due_date": "2024-02-15",
    "payment_terms": "30 jours",
    "amount_ht": 10000.00,
    "total_tva": 2000.00,
    "amount_ttc": 12000.00,
    "currency": "MAD",
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
    "notes": "Paiement par virement bancaire",
    "payment_mode": "Virement bancaire",
    "reference": "BC-456"
  },
  "confidence": 95,
  "warnings": []
}
```

## 🧪 Test

### Vérifier la connexion

```bash
php artisan purchase-invoice:test-gemini --check
```

### Analyser une facture

```bash
php artisan purchase-invoice:test-gemini /path/to/invoice.pdf
```

### Mode verbose (JSON brut)

```bash
php artisan purchase-invoice:test-gemini /path/to/invoice.pdf -v
```

## 📝 Prompt d'Analyse

Le prompt système utilisé est structuré pour demander à Gemini d'extraire:

### Sections

1. **Fournisseur/Supplier**
   - Nom légal de l'entreprise
   - ICE (Identifiant Commun de l'Entreprise)
   - IF (Identifiant Fiscal)
   - Adresse complète
   - Téléphone et Email

2. **Détails Facture**
   - Numéro de facture fournisseur
   - Date de facturation (YYYY-MM-DD)
   - Date d'échéance
   - Conditions de paiement

3. **Montants**
   - Total HT (Hors Taxes)
   - Total TVA (Taxe sur la Valeur Ajoutée)
   - Total TTC (Toutes Taxes Comprises)
   - Devise (MAD, EUR, USD...)

4. **Lignes d'Articles**
   - Désignation/Description
   - Quantité
   - Prix unitaire
   - Taux de TVA (%)
   - Total HT par ligne
   - Total TVA par ligne

5. **Métadonnées**
   - Notes et conditions
   - Mode de paiement
   - Références

### Score de Confiance

- **95-100%**: Tous les champs critiques extraits avec certitude
- **75-94%**: La plupart des champs extraits, quelques ambiguïtés
- **50-74%**: Extraction basique, vérification manuelle recommandée
- **<50%**: Échec de l'extraction ou très incertain

## 🔒 Sécurité

- La clé API est stockée dans `.env` (jamais commitée)
- La connexion utilise HTTPS
- Les fichiers sont convertis en base64 pour l'envoi
- Aucune donnée n'est stockée par Gemini après traitement

## 📊 Limites

- **Taille max fichier**: 20MB
- **Formats supportés**: PDF, JPEG, PNG, WebP, BMP, TIFF
- **Durée moyenne**: 10-30 secondes par analyse
- **Tier gratuit**: 15 requêtes/minute (suffisant pour usage normal)

## 🔄 Workflow Frontend

```javascript
// 1. Upload et analyse
const formData = new FormData();
formData.append('file', file);

const response = await axios.post('/api/purchase-invoices/analyze', formData);
const extractedData = response.data.data;

// 2. Pré-remplir le formulaire
form.fournisseur_name = extractedData.fournisseur.name;
form.supplier_invoice_number = extractedData.supplier_invoice_number;
form.invoice_date = extractedData.invoice_date;
form.items = extractedData.items;

// 3. Utilisateur valide/corrige
// 4. Soumission finale vers POST /api/purchase-invoices
```

## 🛠️ Maintenance

### Mettre à jour la clé API

```bash
# .env
GEMINI_API_KEY=nouvelle_clé_api
```

### Changer le modèle

Modifier dans `GeminiAIService.php`:

```php
const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
```

### Ajuster le prompt

Modifier la méthode `getAnalysisPrompt()` dans `GeminiAIService.php`.

## 📚 Ressources

- [Google Gemini API Docs](https://ai.google.dev/docs)
- [Get API Key](https://makersuite.google.com/app/apikey)
- [Gemini Flash 1.5](https://ai.google.dev/models/gemini)

---

**Créé le**: 2026-07-16
**Version**: 1.0.0
**Auteur**: Facture App Development Team
