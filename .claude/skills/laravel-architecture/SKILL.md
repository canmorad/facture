# Laravel Architecture Skill - Facture App

## Core Architecture

### 1. Service Layer Pattern (Obligatoire)

**Toute la logique métier est dans `App\Services`, jamais dans les contrôleurs.**

```php
// ❌ INTERDIT - Logique dans le contrôleur
class InvoiceController extends Controller
{
    public function store(Request $request) {
        $invoice = Invoice::create([...]); // NON
    }
}

// ✅ OBLIGATOIRE - Logique dans le Service
class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function store(InvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create-document');
        $document = $this->invoiceService->createDraft($request->validated());
        return response()->json($document, 201);
    }
}
```

**Structure d'un Service :**
- Constructor injection des dépendances (autres Services uniquement)
- `getAll()` - liste avec filtres optionnels
- `getCreationData()` - données pour formulaire de création
- `createDraft(array $validated)` - création brouillon
- `finalize(int $id)` - finalisation + numérotation
- `send(int $id)` - envoi
- Actions spécifiques (`markPaid`, `cancel`, `updateMetadata`, `updateItems`)
- `getAvailableActions(int $id)` - actions disponibles selon statut
- Méthodes protégées: `getCompanyId()`, `getCompany()`

### 2. DB Transactions (Obligatoire)

```php
DB::beginTransaction();
try {
    $document = $this->documentService->create([...]);
    $invoice = Invoice::create([...]);
    DB::commit();
    return $document->load('customer.customerable', 'items', 'documentable');
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}
```

### 3. Controller Pattern

```php
class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    public function store(InvoiceRequest $request): JsonResponse
    {
        Gate::authorize('create-document'); // Permission check FIRST
        try {
            $document = $this->invoiceService->createDraft($request->validated());
            return response()->json($document, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Invoice store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Message en français'], 500);
        }
    }
}
```

**Règles Controller :**
- `__construct` injecte UNIQUEMENT le Service
- Chaque méthode commence par `Gate::authorize()`
- try/catch avec 3 niveaux: `\InvalidArgumentException` (422), `\RuntimeException` (422), `\Throwable` (500)
- Messages d'erreur toujours en français
- Pas d'accès direct aux Models (sauf `show()` où `Document::where...` est toléré)

### 4. Config-Driven Permissions

```php
// config/permissions.php
return [
    'roles' => [
        'owner' => ['view-customers', 'create-customer', ...],
        'manager' => ['view-customers', 'create-customer', ...],
        'accountant' => ['view-customers', 'view-products', ...],
        'viewer' => ['view-customers', 'view-products', ...],
    ],
];
```

```php
// Dans le contrôleur
Gate::authorize('view-documents');

// Dans User model (via HasPermissions trait)
$user->hasPermission('create-document'); // lit config, pas de DB
$user->isOwnerForCurrentCompany();
```

### 5. Polymorphic Documents

```php
// Document morphs to Invoice, Quote, DeliveryNote, PurchaseOrder, CreditNote
$document = Document::create([
    'documentable_type' => Invoice::class,
    'documentable_id' => $invoice->id,
]);

// Querying
Document::where('documentable_type', Invoice::class)->get();
$invoice = $document->documentable; // Morph relation
```

### 6. State Machine

```php
// App\Models\Traits\HasStateMachine
$invoice->transitionTo('FINALIZED'); // DRAFT -> FINALIZED
$invoice->transitionTo('SENT');      // FINALIZED -> SENT
$invoice->transitionTo('PAID');      // SENT -> PAID
```

### 7. Multi-Company (Header-Based)

```php
// Middleware: CheckCompanyHeader
// Header: X-Company-Id
$companyId = config('app.current_company_id');

// Dans les Services
protected function getCompanyId(): int
{
    return config('app.current_company_id')
        ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
}
```

### 8. Form Requests (Validation)

```php
class InvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
```

### 9. Routes API

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'check.company'])->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{id}/finalize', [InvoiceController::class, 'finalize']);
    // Jamais de Route::resource pour les documents
});
```

### 10. Traits

```php
// App\Traits\HasPermissions - lecture config/permissions.php
// App\Traits\LogsActivityTrait - logging automatique
// App\Traits\HasMedia - gestion fichiers
// App\Traits\MediaUpload - upload avec Spatie MediaLibrary
```

### 11. Sécurité

- Middleware `auth:sanctum` sur toutes les routes
- Middleware `check.company` vérifie X-Company-Id
- Middleware `CheckIfOwner` pour actions propriétaire uniquement
- `Gate::authorize()` dans chaque méthode de contrôleur
- Pas de `$request->user()` dans les Services (utiliser config)
- Messages jamais en anglais

## Interdictions

- ❌ Logique métier dans les contrôleurs
- ❌ Requêtes SQL directes dans les contrôleurs
- ❌ Messages d'erreur en anglais
- ❌ Accès à `$request->user()` dans les Services
- ❌ `Route::resource` pour les ressources document
- ❌ Tables `permissions` et `role_permissions` (supprimées définitivement)
- ❌ Comments dans le code (règle stricte)
- ❌ `Model::with('permissions')` (relation supprimée)