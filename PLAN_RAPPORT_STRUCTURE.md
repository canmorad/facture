# 📋 PLAN DE RAPPORT - STRUCTURE DÉTAILLÉE
## Projet de Fin d'Études : Facture.app - SaaS ERP Multitenant

---

## 📑 TABLE DES MATIÈRES - ROADMAP COMPLÈTE

### FRONT MATIER (Pages liminaires)

#### 1. Page de titre
- ✅ Logo Devosoft
- ✅ Titre du projet
- ✅ Type de projet (PFE)
- ✅ Année universitaire

#### 2. Remerciements
- ✅ Remerciements à l'entreprise d'accueil (Devosoft)
- ✅ Remerciements aux encadrants
- ✅ Remerciements aux enseignants

#### 3. Dédicaces
- ✅ Dédicaces personnelles

#### 4. Résumé (Abstract)
- ✅ Résumé en français (300-500 mots)
- ✅ Abstract in English (300-500 mots)
- ✅ Mots-clés en français et anglais

#### 5. Liste des figures
- ✅ Liste de toutes les figures avec numéros et pages
- ✅ Se référer à la section "Table des Figures" ci-dessous

#### 6. Liste des tableaux
- ✅ Liste de tous les tableaux avec numéros et pages
- ✅ Se référer à la section "Table des Tableaux" ci-dessous

#### 7. Liste des abréviations
- ✅ API, CRM, ERP, SaaS, B2B, B2C, TVA, HT, TTC, etc.

---

## 📖 CHAPITRE 1 : INTRODUCTION GÉNÉRALE

### 1.1 Contexte et Problématique
#### Checklist technique à couvrir :
- ✅ Contexte de la digitalisation des PME en Afrique
- ✅ Problèmes de gestion documentaire et facturation
- ✅ Besoins de gestion multientreprises (multitenant)
- ✅ Contraintes réglementaires de facturation

#### 1.2 Présentation de l'entreprise d'accueil
#### Checklist Devosoft :
- ✅ Historique et mission de Devosoft
- ✅ Positionnement sur le marché SaaS africain
- ✅ Portfolio produit (existant et envisagé)

#### 1.3 Objectifs du projet
#### Checklist objectifs :
- ✅ Objectif principal : Développement d'un ERP SaaS de facturation
- ✅ Objectifs spécifiques :
  - Architecture multitenant
  - Gestion complète du cycle de vie documentaire
  - Conformité fiscale
  - Expérience utilisateur responsive

#### 1.4 Méthodologie de travail
#### Checklist méthodologie :
- ✅ Approche agile/scrum
- ✅ Outils de collaboration
- ✅ Environnement de développement

#### 1.5 Plan du rapport
- ✅ Structure globale du document

---

## 📚 CHAPITRE 2 : ÉTAT DE L'ART

### 2.1 Les systèmes ERP
#### Checklist ERP :
- ✅ Définition et caractéristiques des ERP
- ✅ Types d'ERP (monolithiques, modulaires, SaaS)
- ✅ ERP open source vs propriétaires
- ✅ ERP pour PME en Afrique

### 2.2 Les solutions de facturation électronique
#### Checklist facturation :
- ✅ Évolution de la facturation papier vers électronique
- ✅ Normes de facturation électronique
- ✅ Solutions existantes (analyse comparative)
- ✅ Limites des solutions actuelles

### 2.3 Architectures logicielles pour SaaS
#### Checklist architectures :
- ✅ Monolithique vs Microservices
- ✅ Multitenancy (shared database vs separate databases)
- ✅ API REST et principes RESTful

### 2.4 Technologies web modernes
#### Checklist technologies :
- ✅ Écosystème PHP/Laravel
- ✅ Frameworks JavaScript (Vue.js, React, Angular)
- ✅ Base de données relationnelles vs NoSQL
- ✅ Authentification JWT/OAuth

---

## 🏗️ CHAPITRE 3 : ANALYSE ET SPÉCIFICATION

### 3.1 Analyse des besoins fonctionnels
#### Checklist besoins fonctionnels :
- ✅ **Gestion des entreprises et utilisateurs**
  - Inscription multitenant
  - Gestion des rôles et permissions
  - Système d'invitations

- ✅ **Gestion clients/fournisseurs**
  - Clients B2B (personnes morales)
  - Clients B2C (particuliers)
  - Fournisseurs

- ✅ **Catalogue produits/services**
  - Catégories de produits
  - Gestion des taux de TVA
  - Types de produits

- ✅ **Documents commerciaux**
  - Devis (Quotes)
  - Proformas
  - Factures (standard, acompte, solde)
  - Avoirs
  - Bons de livraison
  - Bons de commande
  - Factures fournisseurs

- ✅ **Gestion des paiements**
  - Enregistrement de paiements
  - Modes de paiement
  - Conditions de paiement
  - Remises en banque
  - Caisses enregistreuses

- ✅ **Paramétrage documents**
  - Numérotation automatique
  - Thèmes et templates
  - Paramètres par type de document

- ✅ **Fonctionnalités avancées**
  - Factures récurrentes
  - Dépenses et charges
  - Reporting TVA
  - Dashboard

### 3.2 Analyse des besoins non-fonctionnels
#### Checklist besoins non-fonctionnels :
- ✅ Performance
- ✅ Sécurité et authentification
- ✅ Scalabilité
- ✅ Disponibilité
- ✅ Expérience utilisateur (UX/UI)

### 3.3 Acteurs du système
#### Checklist acteurs :
- ✅ Diagramme des acteurs
- ✅ Rôles et permissions par acteur
- ✅ Matrix des permissions

### 3.4 Use Cases
#### Checklist use cases :
- ✅ Use Case: Gestion entreprises
- ✅ Use Case: Gestion documents commerciaux
- ✅ Use Case: Gestion paiements
- ✅ Use Case: Reporting

---

## 🎨 CHAPITRE 4 : CONCEPTION

### 4.1 Architecture globale du système
#### Checklist architecture :
- ✅ **Diagramme d'architecture globale**
  - Frontend Vue.js SPA
  - Backend Laravel API
  - Base de données
  - Services externes (Email, AI)

- ✅ **Architecture multitenant**
  - Stratégie de séparation des données
  - Gestion du contexte entreprise

- ✅ **Architecture des documents**
  - Pattern polymorphique
  - Relations inter-documents

### 4.2 Modèle de données
#### Checklist base de données :
- ✅ **Schéma de base de données complet**
  - Tables: users, companies, user_companies
  - Tables: customers (b2b_customers, b2c_customers)
  - Tables: suppliers, fournisseurs
  - Tables: products, product_categories
  - Tables: documents, document_items
  - Tables: quotes, invoices, credit_notes, etc.
  - Tables: payments, payment_documents
  - Tables: bank_remittances
  - Tables: cash_registers, cash_transactions
  - Tables: numbering_series
  - Tables: document_settings, document_themes

- ✅ **Diagrammes de classes (UML)**
  - Modèle Document polymorphique
  - Modèle Customer polymorphique
  - Modèle de configuration

### 4.3 Conception de l'API
#### Checklist API :
- ✅ **Architecture RESTful**
  - Conventions de nommage
  - Codes HTTP
  - Format des réponses

- ✅ **Endpoints par module**
  - Authentification: /login, /register, /logout
  - Entreprises: /companies, /company-settings
  - Clients: /customers
  - Produits: /products, /product-categories
  - Devis: /quotes
  - Factures: /invoices
  - Avoirs: /credit-notes
  - Paiements: /payments
  - Remises: /bank-remittances
  - Caisses: /cash-registers

### 4.4 Maquettes IHM
#### Checklist maquettes :
- ✅ **Wireframes par écran**
  - Login/Register
  - Dashboard
  - Liste documents (chaque type)
  - Création document
  - Paramétrage
  - Rapports

### 4.5 Flux de travail
#### Checklist workflows :
- ✅ **Cycle de vie des documents**
  - États et transitions
  - Conversions inter-documents

- ✅ **Workflow de validation**
  - Finalisation → Envoi → Paiement

---

## 💻 CHAPITRE 5 : RÉALISATION

### 5.1 Environnement de développement
#### Checklist environnement :
- ✅ Configuration materielle requise
- ✅ Configuration logicielle (voir section Matériel & Outils)
- ✅ Stack technique détaillée

### 5.2 Implémentation Backend
#### Checklist backend :
- ✅ **Configuration Laravel**
  - Structure des dossiers
  - Service providers
  - Middlewares (auth, company check)

- ✅ **Modèles et relations**
  - Modèle Document et polymorphisme
  - Modèles Customer, Supplier
  - Relations inter-documents

- ✅ **Controllers et Services**
  - DocumentController et services dédiés
  - QuoteService, InvoiceService, etc.
  - Calculs automatiques (totaux, TVA)

- ✅ **Système de numérotation**
  - NumberingSerie
  - Auto-incrémentation par type

- ✅ **Workflow documentaire**
  - États et transitions
  - Historique (DocumentWorkflowHistory)

- ✅ **API REST**
  - Routes (routes/api.php)
  - Validation (Requests)
  - Gestion des erreurs

- ✅ **Authentification et autorisation**
  - Laravel Sanctum (tokens)
  - Rôles et permissions
  - Middleware check.company

- ✅ **Fonctionnalités spéciales**
  - Analysis AI (GeminiAIService)
  - Génération PDF (DOMPDF)
  - Envoi d'emails
  - Recurring invoices (cron job)

### 5.3 Implémentation Frontend
#### Checklist frontend :
- ✅ **Configuration Vue.js**
  - Vite, Vue Router, Pinia
  - Structure des dossiers (components, views, services, stores)

- ✅ **Layout et navigation**
  - AuthenticatedLayout
  - NavigationGuard (router guard.js)

- ✅ **Components réutilisables**
  - DocumentLineItem, DocumentTotals
  - ProductForm, CustomerForm
  - SignaturePad, SendEmailModal
  - Dashboard components

- ✅ **Views par module**
  - Quotes, Proformas, Invoices, etc.
  - Purchase Orders, Delivery Notes
  - Bank Remittances, Cash Registers
  - Settings

- ✅ **Services API**
  - services/documentApi.js
  - services/paymentApi.js
  - services/bankRemittanceApi.js

- ✅ **Stores Pinia**
  - stores/auth.js
  - Gestion d'état globale

- ✅ **Utilisation de composants tiers**
  - VueForm/Multiselect
  - Chart.js + vue-chartjs (dashboard)
  - Tailwind CSS 4

### 5.4 Base de données
#### Checklist BDD :
- ✅ Migrations Laravel
- ✅ Seeders (données de test)
- ✅ Relations et contraintes

### 5.5 Tests
#### Checklist tests :
- ✅ Tests unitaires (PHPUnit)
- ✅ Tests d'intégration API
- ✅ Tests frontend (optionnel)

---

## 🧪 CHAPITRE 6 : RÉSULTATS ET DISCUSSION

### 6.1 Fonctionnalités implémentées
#### Checklist résultats :
- ✅ Module Entreprise et Utilisateurs ✓
- ✅ Module Clients/Fournisseurs ✓
- ✅ Module Produits ✓
- ✅ Module Devis ✓
- ✅ Module Factures ✓
- ✅ Module Avoirs ✓
- ✅ Module Paiements ✓
- ✅ Module Remises Bancaires ✓
- ✅ Module Caisses ✓
- ✅ Module Reporting ✓

### 6.2 Captures d'écran
#### Checklist captures :
- ✅ Écran login/register
- ✅ Dashboard avec graphiques
- ✅ Liste documents (chaque type)
- ✅ Création/édition document
- ✅ Paramétrage
- ✅ Preview et impression PDF

### 6.3 Analyse de performance
#### Checklist performance :
- ✅ Temps de réponse API
- ✅ Optimisations apportées
- ✅ Gestion du cache

### 6.4 Discussion technique
#### Checklist discussion :
- ✅ Choix architecturaux justifiés
- ✅ Polymorphisme vs héritage
- ✅ Multitenant approach
- ✅ Limites et améliorations possibles

---

## 🔒 CHAPITRE 7 : SÉCURITÉ ET VALIDATION

### 7.1 Sécurité implémentée
#### Checklist sécurité :
- ✅ Authentification Sanctum
- ✅ CORS configuration
- ✅ Validation des inputs
- ✅ Protection CSRF
- ✅ SQL injection prevention (Eloquent)
- ✅ XSS protection

### 7.2 Validation fiscale
#### Checklist fiscal :
- ✅ FiscalComplianceService
- ✅ Calculs TVA
- ✅ Rapport TVA
- ✅ Conformité réglementaire

---

## 🚀 CHAPITRE 8 : DÉPLOIEMENT

### 8.1 Stratégie de déploiement
#### Checklist déploiement :
- ✅ Environnement de production
- ✅ Configuration serveur
- ✅ Processus CI/CD (si applicable)
- ✅ Monitoring

---

## 📝 CHAPITRE 9 : CONCLUSION ET PERSPECTIVES

### 9.1 Conclusion
#### Checklist conclusion :
- ✅ Résumé des réalisations
- ✅ Objectifs atteints
- ✅ Apports personnels

### 9.2 Perspectives
#### Checklist perspectives :
- ✅ Améliorations futures
- ✅ Nouvelles fonctionnalités envisagées
- ✅ Évolutivité de la solution

---

## 📚 BIBLIOGRAPHIE

### Bibliographie technique
- ✅ Documentation Laravel
- ✅ Documentation Vue.js
- ✅ Articles sur architectures SaaS
- ✅ Normes de facturation

### Bibliographie académique
- ✅ Articles de recherche
- ✅ Thèses et mémoires connexes

---

## 📎 ANNEXES

### Annexe A : Guide d'utilisation
- ✅ Manuel utilisateur
- ✅ Screenshots commentés

### Annexe B : Documentation technique
- ✅ Documentation API complète
- ✅ Schéma de BDD détaillé
- ✅ Scripts SQL

### Annexe C : Code source
- ✅ Extrait de code significatif
- ✅ Commentaires GitHub

### Annexe D : Tests
- ✅ Rapports de tests
- ✅ Résultats

---

## 🖼️ TABLE DES FIGURES (Placeholders à générer)

### Chapitre 1 - Introduction
- Figure 1.1 : Cartographie du marché SaaS en Afrique
- Figure 1.2 : Organigramme de Devosoft

### Chapitre 2 - État de l'art
- Figure 2.1 : Comparatif solutions de facturation existantes
- Figure 2.2 : Architecture SaaS typique
- Figure 2.3 : Évolution des technologies web

### Chapitre 3 - Analyse
- Figure 3.1 : Diagramme des cas d'utilisation (Use Case global)
- Figure 3.2 : Diagramme des acteurs du système
- Figure 3.3 : Matrix des rôles et permissions
- Figure 3.4 : Workflow de gestion documentaire

### Chapitre 4 - Conception
- Figure 4.1 : **Architecture globale du système** (Vue d'ensemble)
- Figure 4.2 : **Diagramme de séquence - Authentification**
- Figure 4.3 : **Diagramme de séquence - Création document**
- Figure 4.4 : **MCD - Schéma de base de données** (Schéma complet)
- Figure 4.5 : **Diagramme de classes - Modèle Document**
- Figure 4.6 : **Diagramme de classes - Modèle User/Company**
- Figure 4.7 : **Diagramme de classes - Modèle Customer**
- Figure 4.8 : **Diagramme d'activité - Workflow document**
- Figure 4.9 : **Wireframe - Page Login**
- Figure 4.10 : **Wireframe - Dashboard**
- Figure 4.11 : **Wireframe - Liste documents**
- Figure 4.12 : **Wireframe - Création facture**
- Figure 4.13 : **Wireframe - Paramétrage**
- Figure 4.14 : **Maquette finale - Dashboard**
- Figure 4.15 : **Maquette finale - Liste factures**

### Chapitre 5 - Réalisation
- Figure 5.1 : **Structure du projet** (Arborescence backend)
- Figure 5.2 : **Structure du projet** (Arborescence frontend)
- Figure 5.3 : **Diagramme de composants - Frontend Vue.js**
- Figure 5.4 : **Architecture des Services Laravel**
- Figure 5.5 : **Flux de données - Création document**
- Figure 5.6 : **Screenshot - Page Login**
- Figure 5.7 : **Screenshot - Dashboard avec graphiques**
- Figure 5.8 : **Screenshot - Liste des devis**
- Figure 5.9 : **Screenshot - Création d'une facture**
- Figure 5.10 : **Screenshot - Aperçu PDF document**
- Figure 5.11 : **Screenshot - Paramétrage numérotation**
- Figure 5.12 : **Screenshot - Gestion des clients**
- Figure 5.13 : **Screenshot - Remises bancaires**
- Figure 5.14 : **Screenshot - Caisse enregistreuse**
- Figure 5.15 : **Screenshot - Rapport TVA**

### Chapitre 6 - Résultats
- Figure 6.1 : **Comparatif Avant/Après** (Gestion manuelle vs Facture.app)
- Figure 6.2 : Graphique des temps de réponse API
- Figure 6.3 : Distribution des types de documents créés

### Chapitre 7 - Sécurité
- Figure 7.1 : Architecture de sécurité
- Figure 7.2 : Flux d'authentification Sanctum

### Chapitre 8 - Déploiement
- Figure 8.1 : Architecture de production
- Figure 8.2 : Processus de déploiement

### Annexes
- Figure A.1 : Guide d'utilisation - Étape 1
- Figure A.2 : Guide d'utilisation - Étape 2
- Figure C.1 : Extrait de code - Document polymorphique
- Figure C.2 : Extrait de code - Calcul automatique

---

## 📊 TABLE DES TABLEAUX (Placeholders à générer)

### Chapitre 2
- Tableau 2.1 : Comparatif solutions ERP open source
- Tableau 2.2 : Frameworks PHP - Comparatif
- Tableau 2.3 : Frameworks JavaScript - Comparatif

### Chapitre 3
- Tableau 3.1 : Besoins fonctionnels - Module Entreprise
- Tableau 3.2 : Besoins fonctionnels - Module Documents
- Tableau 3.3 : Besoins fonctionnels - Module Paiements
- Tableau 3.4 : Matrix des acteurs et use cases
- Tableau 3.5 : Priorités des fonctionnalités

### Chapitre 4
- Tableau 4.1 : Description des tables de la base de données
- Tableau 4.2 : Routes API - Module Authentification
- Tableau 4.3 : Routes API - Module Documents
- Tableau 4.4 : Routes API - Module Paiements
- Tableau 4.5 : États et transitions des documents
- Tableau 4.6 : Rôles et permissions détaillés

### Chapitre 5
- Tableau 5.1 : Technologies utilisées - Backend
- Tableau 5.2 : Technologies utilisées - Frontend
- Tableau 5.3 : Configuration serveur requise
- Tableau 5.4 : Structure des dossiers Laravel
- Tableau 5.5 : Services Laravel implémentés
- Tableau 5.6 : Components Vue.js créés

### Chapitre 6
- Tableau 6.1 : Fonctionnalités implémentées - État
- Tableau 6.2 : Résultats tests de performance
- Tableau 6.3 : Couverture de tests

---

## 🛠️ MATÉRIEL & OUTILS - SPÉCIFICATIONS TECHNIQUES

### Backend - Stack Laravel
| Composant | Version/Spécification | Fichier de référence |
|-----------|----------------------|---------------------|
| PHP | ^8.3 | backend/composer.json |
| Laravel Framework | ^13.8 | backend/composer.json |
| Laravel Sanctum | ^4.0 | backend/composer.json |
| Laravel Breeze | ^2.4 | backend/composer.json |
| DOMPDF | ^3.1 | backend/composer.json |
| Spatie ActivityLog | ^4.12 | backend/composer.json |
| PHPUnit | ^12.5.12 | backend/composer.json |
| Base de données | SQLite (défaut) / MySQL | backend/.env.example |
| Queue | Database | backend/.env.example |
| Cache | Database | backend/.env.example |

### Frontend - Stack Vue.js
| Composant | Version/Spécification | Fichier de référence |
|-----------|----------------------|---------------------|
| Vue.js | ^3.5.32 | frontend/package.json |
| Vue Router | ^5.0.4 | frontend/package.json |
| Pinia | ^3.0.4 | frontend/package.json |
| Vite | ^8.0.8 | frontend/package.json |
| Tailwind CSS | ^4.3.0 | frontend/package.json |
| Axios | ^1.16.1 | frontend/package.json |
| Chart.js | ^4.5.1 | frontend/package.json |
| vue-chartjs | ^5.3.3 | frontend/package.json |
| SweetAlert2 | ^11.26.25 | frontend/package.json |
| signature_pad | ^5.1.3 | frontend/package.json |
| @vueform/multiselect | ^2.6.11 | frontend/package.json |

### Services et APIs
| Service | Description | Configuration |
|---------|-------------|---------------|
| Gemini AI | Analyse factures fournisseurs | GEMINI_API_KEY, GEMINI_MODEL |
| Mail | Envoi documents | MAIL_MAILER, MAIL_HOST |

### Structure des Controllers Backend
```
backend/app/Http/Controllers/
├── Auth/
│   ├── AuthenticatedSessionController.php
│   ├── EmailVerificationNotificationController.php
│   ├── NewPasswordController.php
│   ├── PasswordResetLinkController.php
│   ├── RegisteredUserController.php
│   ├── UserStatusController.php
│   └── VerifyEmailController.php
├── BankAccountController.php
├── BankRemittanceController.php
├── CashRegisterController.php
├── CompanyController.php
├── CreditNoteController.php
├── CustomerController.php
├── DashboardController.php
├── DepositController.php
├── DeliveryNoteController.php
├── DocumentController.php
├── DocumentPreviewController.php
├── DocumentSettingController.php
├── DocumentThemeController.php
├── ExpenseController.php
├── InvitationController.php
├── InvoiceController.php
├── LateFeeInterestController.php
├── NumberingSerieController.php
├── PaymentController.php
├── PaymentConditionController.php
├── PaymentDocumentController.php
├── PaymentModeController.php
├── ProductController.php
├── ProductCategoryController.php
├── ProformaController.php
├── PurchaseInvoiceController.php
├── PurchaseOrderController.php
├── QuoteController.php
├── RecurringInvoiceController.php
├── SendDocumentController.php
├── SupplierController.php
├── TaxRateController.php
└── TvaReportController.php
```

### Structure des Services Backend
```
backend/app/Services/
├── ActivityLogService.php
├── BankRemittanceService.php
├── BankRemittanceWorkflowService.php
├── CashRegisterService.php
├── CreditNoteService.php
├── DashboardService.php
├── DeliveryNoteService.php
├── DepositService.php
├── DocumentCalculationService.php
├── DocumentItemService.php
├── DocumentService.php
├── DocumentWorkflowService.php
├── ExpenseService.php
├── FiscalComplianceService.php
├── GeminiAIService.php
├── InvoiceService.php
├── InvitationService.php
├── NumberingService.php
├── PaymentService.php
├── ProformaService.php
├── PurchaseInvoiceService.php
├── PurchaseOrderService.php
├── QuoteService.php
├── RecurringInvoiceService.php
├── TvaReportService.php
└── WithholdingTaxCalculationService.php
```

### Structure des Modèles Backend
```
backend/app/Models/
├── BankAccount.php
├── BankRemittance.php
├── B2bCustomer.php
├── B2cCustomer.php
├── CashRegister.php
├── CashRegisterSession.php
├── CashTransaction.php
├── Company.php
├── CreditNote.php
├── Customer.php
├── Delivery.php
├── DeliveryNote.php
├── Deposit.php
├── Document.php
├── DocumentItem.php
├── DocumentLink.php
├── DocumentRelationship.php
├── DocumentTheme.php
├── DocumentWorkflowHistory.php
├── Expense.php
├── Fournisseur.php
├── Invoice.php
├── InvoiceDeduction.php
├── Invitation.php
├── LateFeeInterest.php
├── Media.php
├── NumberingSerie.php
├── Payment.php
├── PaymentCondition.php
├── PaymentDocument.php
├── PaymentMode.php
├── PaymentSetting.php
├── Product.php
├── ProductCategory.php
├── Proforma.php
├── PurchaseInvoice.php
├── PurchaseInvoiceItem.php
├── PurchaseOrder.php
├── Quote.php
├── RecurringInvoice.php
├── Role.php
├── Supplier.php
├── TaxRate.php
├── User.php
└── UserCompany.php
```

### Structure des Vues Frontend
```
frontend/src/views/
├── Auth/
│   ├── AcceptInvitation.vue
│   ├── Login.vue
│   ├── Register.vue
│   └── VerifyEmail.vue
├── AccountSecurity.vue
├── balance-invoices/
│   ├── BalanceInvoiceIndex.vue
│   └── CreateBalanceInvoice.vue
├── bank-remittances/
│   ├── BankRemittanceIndex.vue
│   ├── CreateBankRemittance.vue
│   ├── PrintBankRemittance.vue
│   └── ShowBankRemittance.vue
├── cash-registers/
│   ├── CashRegisterDashboard.vue
│   └── CashRegisterIndex.vue
├── Clients.vue
├── credit-notes/
│   ├── CreateCreditNote.vue
│   └── CreditNoteIndex.vue
├── Dashboard.vue
├── delivery-notes/
│   ├── CreateDeliveryNote.vue
│   └── DeliveryNoteIndex.vue
├── deposits/
│   ├── CreateDeposit.vue
│   └── DepositIndex.vue
├── DocumentPreview.vue
├── DocumentPrint.vue
├── DocumentSend.vue
├── Home.vue
├── invoices/
│   ├── CreateInvoice.vue
│   └── InvoiceIndex.vue
├── proformas/
│   ├── CreateProforma.vue
│   └── ProformaIndex.vue
├── purchase-invoices/
│   ├── PurchaseInvoicePreview.vue
│   └── PurchaseInvoices.vue
├── purchase-orders/
│   ├── CreatePurchaseOrder.vue
│   └── PurchaseOrderIndex.vue
├── quotes/
│   ├── CreateQuote.vue
│   └── QuoteIndex.vue
├── recurring-invoices/
│   ├── CreateRecurringInvoice.vue
│   └── RecurringInvoiceIndex.vue
├── RegisterPayment.vue
├── settings/
│   ├── BankAccounts.vue
│   ├── Coordinates.vue
│   ├── Documents.vue
│   ├── LateFeeInterests.vue
│   ├── Numbering.vue
│   ├── Organizations.vue
│   ├── PaymentConditions.vue
│   ├── PaymentModes.vue
│   ├── ProductTypes.vue
│   ├── TaxRates.vue
│   ├── Theme.vue
│   └── Users.vue
├── suppliers/
│   └── SupplierIndex.vue
└── Products.vue
```

### Structure des Routes API (Endpoints principaux)

#### Authentication
- `POST /login` - Connexion
- `POST /register` - Inscription
- `POST /logout` - Déconnexion
- `GET /user-status` - Statut utilisateur
- `GET /user-companies` - Liste entreprises de l'utilisateur

#### Entreprises & Utilisateurs
- `POST /companies` - Créer entreprise
- `GET /company-settings` - Paramètres entreprise
- `POST /company-settings` - Mettre à jour paramètres
- `GET /company/users` - Liste utilisateurs
- `POST /company/users/invite` - Inviter utilisateur
- `DELETE /organizations/{id}/leave` - Quitter organisation

#### Clients & Fournisseurs
- `GET /customers` - Liste clients
- `POST /customers` - Créer client
- `PUT /customers/{id}` - Modifier client
- `DELETE /customers/{id}` - Supprimer client
- `GET /suppliers` - Liste fournisseurs
- `POST /suppliers` - Créer fournisseur

#### Produits
- `GET /products` - Liste produits
- `POST /products` - Créer produit
- `PUT /products/{id}` - Modifier produit
- `DELETE /products/{id}` - Supprimer produit
- `GET /product-categories` - Liste catégories

#### Devis (Quotes)
- `GET /quotes` - Liste devis
- `GET /quotes/{id}` - Détail devis
- `POST /quotes` - Créer devis
- `PUT /quotes/{id}` - Modifier devis
- `PUT /quotes/{id}/finalize` - Finaliser
- `PUT /quotes/{id}/send` - Envoyer
- `PUT /quotes/{id}/sign` - Signer
- `POST /quotes/{id}/convert-to-invoice` - Convertir en facture
- `POST /quotes/{id}/convert-to-purchase-order` - Convertir en bon commande
- `POST /quotes/{id}/create-delivery-note` - Créer bon livraison

#### Proformas
- `GET /proformas` - Liste proformas
- `POST /proformas` - Créer proforma
- `PUT /proformas/{id}/finalize` - Finaliser
- `PUT /proformas/{id}/send` - Envoyer
- `POST /proformas/{id}/convert-to-invoice` - Convertir en facture

#### Factures (Invoices)
- `GET /invoices` - Liste factures
- `POST /invoices` - Créer facture
- `PUT /invoices/{id}/finalize` - Finaliser
- `PUT /invoices/{id}/send` - Envoyer
- `PUT /invoices/{id}/mark-overdue` - Marquer en retard
- `POST /invoices/{id}/add-deduction` - Ajouter déduction acompte
- `POST /invoices/{id}/generate-credit-note` - Générer avoir

#### Avoirs (Credit Notes)
- `GET /credit-notes` - Liste avoirs
- `POST /credit-notes` - Créer avoir
- `PUT /credit-notes/{id}/finalize` - Finaliser
- `PUT /credit-notes/{id}/apply` - Appliquer

#### Acomptes (Deposits)
- `GET /deposits` - Liste acomptes
- `POST /deposits` - Créer acompte
- `PUT /deposits/{id}/finalize` - Finaliser
- `PUT /deposits/{id}/mark-paid` - Marquer payé

#### Paiements
- `GET /payments` - Liste paiements
- `POST /payments` - Enregistrer paiement
- `DELETE /payments/{id}` - Supprimer paiement
- `GET /invoices/{id}/payments` - Paiements d'une facture
- `GET /invoices/{id}/payment-summary` - Résumé paiements

#### Remises Bancaires
- `GET /bank-remittances` - Liste remises
- `POST /bank-remittances` - Créer remise
- `PUT /bank-remittances/{id}/finalize` - Finaliser
- `PUT /bank-remittances/{id}/deposit` - Marquer déposée
- `GET /bank-remittances/{id}/preview` - Aperçu

#### Caisses
- `GET /cash-registers` - Liste caisses
- `POST /cash-registers/{id}/open-session` - Ouvrir session
- `POST /cash-registers/{id}/close-session` - Fermer session
- `POST /cash-registers/transactions` - Enregistrer transaction

#### Paramétrage
- `GET /document-settings/{type}` - Paramètres document
- `POST /document-settings` - Créer/modifier paramètres
- `GET /numbering-serie` - Série de numérotation
- `GET /tax-rates` - Taux TVA
- `GET /payment-conditions` - Conditions paiement
- `GET /payment-modes` - Modes paiement
- `GET /bank-accounts` - Comptes bancaires

#### Reporting
- `GET /dashboard` - Dashboard KPIs
- `GET /dashboard/tva-report` - Rapport TVA

---

## 📐 DIAGRAMMES UML À GÉNÉRER

### Diagrammes de Use Case
- UC1 : Gestion des entreprises et utilisateurs
- UC2 : Gestion du catalogue produits
- UC3 : Gestion des clients et fournisseurs
- UC4 : Cycle de vie des documents commerciaux
- UC5 : Gestion des paiements
- UC6 : Reporting et analyses

### Diagrammes de Classes
- DC1 : Modèle User-Company (multitenant)
- DC2 : Modèle Customer (polymorphique B2B/B2C)
- DC3 : Modèle Document (polymorphique)
- DC4 : Modèle Items et relations
- DC5 : Modèle Paiements
- DC6 : Modèle Remises et Caisses

### Diagrammes de Séquence
- DS1 : Authentification et initialisation contexte
- DS2 : Création d'un document (devis)
- DS3 : Workflow de validation document
- DS4 : Conversion inter-documents
- DS5 : Enregistrement paiement
- DS6 : Génération PDF et envoi email

### Diagrammes d'Activité
- DA1 : Workflow document (états et transitions)
- DA2 : Processus de remise en banque
- DA3 : Gestion caisse enregistreuse

### Diagrammes de Composants
- DCMP1 : Architecture frontend Vue.js
- DCMP2 : Architecture backend Laravel
- DCMP3 : Architecture des Services

---

## 📊 SCHÉMAS DE BASE DE DONNÉES

### Tables principales
1. **users** - Utilisateurs
2. **companies** - Entreprises (tenants)
3. **user_companies** - Relation users-companies
4. **customers** - Clients (avec polymorphisme B2B/B2C)
5. **suppliers** - Fournisseurs
6. **fournisseurs** - Fournisseurs (variante)
7. **products** - Catalogue produits
8. **product_categories** - Catégories produits
9. **tax_rates** - Taux de TVA
10. **documents** - Documents (polymorphique)
11. **document_items** - Lignes de documents
12. **quotes** - Devis
13. **proformas** - Proformas
14. **invoices** - Factures
15. **credit_notes** - Avoirs
16. **purchase_orders** - Bons de commande
17. **delivery_notes** - Bons de livraison
18. **deposits** - Acomptes
19. **invoice_deductions** - Dédictions acompte
20. **payments** - Paiements (polymorphique)
21. **payment_documents** - Documents de paiement
22. **bank_remittances** - Remises bancaires
23. **cash_registers** - Caisses
24. **cash_register_sessions** - Sessions caisse
25. **cash_transactions** - Transactions caisse
26. **payment_conditions** - Conditions paiement
27. **payment_modes** - Modes paiement
28. **late_fee_interests** - Pénalités retard
29. **bank_accounts** - Comptes bancaires
30. **numbering_series** - Séries de numérotation
31. **document_settings** - Paramètres documents
32. **document_themes** - Thèmes documents
33. **document_workflow_histories** - Historique workflow
34. **document_links** - Liens inter-documents
35. **document_relationships** - Relations avec montants alloués
36. **invitations** - Invitations utilisateurs
37. **recurring_invoices** - Factures récurrentes
38. **expenses** - Dépenses
39. **media** - Fichiers médias
40. **activity_log** - Journal d'activité
41. **roles** - Rôles utilisateurs

---

## 🎯 POINTS CLÉS À METTRE EN VALEUR DANS LE RAPPORT

### Innovation technique
1. **Architecture polymorphique** pour documents et clients
2. **Multitenancy** basé sur les companies
3. **Workflow flexible** avec états et transitions
4. **Conversion inter-documents** (devis → facture → avoir)
5. **Numérotation automatique** par type de document
6. **Intégration AI** pour analyse factures fournisseurs
7. **Système de remises en banque** innovant
8. **Caisse enregistreuse** intégrée

### Respect des standards académiques
1. Analyse théorique (état de l'art)
2. Méthodologie rigoureuse
3. Conception UML complète
4. Implémentation détaillée
5. Tests et validation
6. Discussion critique

---

*Ce plan de rapport est généré automatiquement basé sur l'analyse du code source et des documents de référence fournis.*
*Date de génération : 2026-07-19*
*Projet : Facture.app - Devosoft*
