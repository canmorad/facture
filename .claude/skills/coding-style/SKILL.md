# Coding Style Skill - Facture App

## Règles Globales

### 1. Zéro Commentaire
Aucun commentaire dans le code. Ni `//`, ni `/* */`, ni PHPDoc. Le code doit être auto-documenté.

### 2. Langue
- **Backend**: Messages d'erreur en **français**
- **Frontend**: Notifications SweetAlert2 en **français**
- **Variables/fonctions**: En **anglais** (ex: `getCreationData`, `markPaid`)

### 3. Naming Conventions

**Backend (PHP/Laravel)**:
- Classes: PascalCase (`InvoiceService`, `CheckCompanyHeader`)
- Méthodes: camelCase (`getAll`, `createDraft`, `getAvailableActions`)
- Variables: camelCase (`$companyId`, `$invoiceService`)
- Tables: snake_case (`user_companies`, `role_permissions`)
- Routes: kebab-case (`/delivery-notes/{id}/convert-to-invoice`)

**Frontend (Vue 3/JS)**:
- Composants: PascalCase (`InvoiceIndex.vue`, `DocumentForm.vue`)
- Composables: `use` + PascalCase (`usePermission.js`, `useDocumentLock.js`)
- Stores: camelCase (`authStore`, `useAuthStore`)
- Variables/fonctions: camelCase (`fetchInvoices`, `selectedStatus`)

### 4. Structure de Fichiers

**Backend**:
```
app/
├── Http/
│   ├── Controllers/Auth/        (AuthenticatedSessionController, UserStatusController)
│   ├── Controllers/             (InvoiceController, QuoteController, ...)
│   ├── Middleware/               (CheckCompanyHeader, CheckIfOwner)
│   └── Requests/                 (InvoiceRequest, DepositRequest)
├── Mail/                         (InvitationMail, SendDocumentMail)
├── Models/                       (Invoice, Document, User, ...)
├── Services/                     (InvoiceService, QuoteService, ...)
└── Traits/                       (HasPermissions, LogsActivityTrait)
```

**Frontend**:
```
src/
├── components/                   (CompanySelect, DocumentForm, DropdownSelect)
├── composables/                  (usePermission, useDocumentLock)
├── helpers/                      (notifications.js)
├── layouts/                      (AuthenticatedLayout, SettingsLayout)
├── router/                       (index.js, guard.js)
├── stores/                       (auth.js)
└── views/
    ├── Auth/                     (Login, Register, AcceptInvitation)
    ├── invoices/                 (InvoiceIndex, CreateInvoice)
    ├── quotes/                   (QuoteIndex, CreateQuote)
    ├── delivery-notes/           (DeliveryNoteIndex, CreateDeliveryNote)
    ├── purchase-orders/          (...)
    ├── deposits/                 (...)
    ├── expenses/                 (...)
    ├── recurring-invoices/       (...)
    └── settings/                 (Theme, Users, Coordinates, ...)
```

### 5. Indentation
- PHP: 4 espaces
- JavaScript/Vue: 2 espaces
- Pas de tabs

### 6. Imports
Toujours en haut du fichier, groupés par type:
1. Framework/Library imports
2. Project imports (`@/stores/auth`, `@/helpers/notifications`)

### 7. Messages d'erreur
```php
// ✅ Correct
throw new \RuntimeException('Aucune entreprise sélectionnée.');
throw new \InvalidArgumentException('Le document n\'est pas une facture.');

// ❌ Interdit
throw new \RuntimeException('No company selected.');