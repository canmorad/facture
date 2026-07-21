<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\BankRemittanceController;
use App\Http\Controllers\BankRemittancePreviewController;
use App\Http\Controllers\PaymentDocumentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Auth\UserStatusController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\NumberingSerieController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\PaymentConditionController;
use App\Http\Controllers\PaymentModeController;
use App\Http\Controllers\LateFeeInterestController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\DocumentThemeController;
use App\Http\Controllers\DocumentSettingController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\BalanceInvoiceController;
use App\Http\Controllers\DocumentPreviewController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SendDocumentController;
use App\Http\Controllers\TvaReportController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ============================================
// PUBLIC AUTHENTICATION ROUTES
// ============================================
// These routes do not require authentication
Route::post('/register', [RegisteredUserController::class, 'store']);

Route::post('/login', [AuthenticatedSessionController::class, 'store']);

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);

Route::post('/reset-password', [NewPasswordController::class, 'store']);

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1']);

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return response()->json(['message' => 'Verification link sent']);
})->middleware(['auth:sanctum', 'throttle:6,1']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum');

// ============================================
// PROTECTED AUTHENTICATION ROUTES
// ============================================
// User profile routes (auth:sanctum only, no company check required)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user-status', UserStatusController::class);

    Route::get('/user/profile', [ProfileController::class, 'getProfile']);
    Route::put('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);

    Route::get('/user-companies', function (Request $request) {
        $user = $request->user();
        $companies = $user->companies()->get();
        return response()->json($companies);
    });
});

// ============================================
// INVITATION ROUTES
// ============================================
Route::get('/invitations/verify/{token}', [InvitationController::class, 'verify']);
Route::post('/invitations/accept', [InvitationController::class, 'accept']);
Route::middleware(['auth:sanctum'])->post('/invitations/accept-existing', [InvitationController::class, 'acceptForExistingUser']);

// ============================================
// DEBUG ROUTE
// ============================================
Route::get('/debug-test', function () {
    return response()->json([
        'message' => 'Debug test successful',
        'headers' => request()->headers->all(),
        'accepts' => request()->getAcceptableContentTypes(),
    ]);
});

// ============================================
// PROTECTED COMPANY ROUTES
// ============================================
// All routes below require authentication AND a valid company context
Route::middleware(['auth:sanctum', 'check.company'])->group(function () {
    // Tax Rates
    Route::apiResource('tax-rates', TaxRateController::class)->except(['destroy']);
    Route::delete('tax-rates/{id}', [TaxRateController::class, 'destroy']);
    Route::patch('tax-rates/{id}/toggle', [TaxRateController::class, 'toggleStatus']);

    // Document Settings
    Route::get('/document-settings/{type}', [DocumentSettingController::class, 'show']);
    Route::post('/document-settings', [DocumentSettingController::class, 'storeOrUpdate']);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Product Categories
    Route::apiResource('product-categories', ProductCategoryController::class);

    // Numbering Series
    Route::get('/numbering-serie', [NumberingSerieController::class, 'show']);
    Route::post('/numbering-serie', [NumberingSerieController::class, 'store']);
    Route::put('/numbering-serie', [NumberingSerieController::class, 'update']);

    // Delivery Notes
    Route::get('/delivery-notes/create', [DeliveryNoteController::class, 'create']);
    Route::post('/delivery-notes', [DeliveryNoteController::class, 'store']);
    Route::put('/delivery-notes/{id}/finalize', [DeliveryNoteController::class, 'finalize']);
    Route::put('/delivery-notes/{id}/send', [DeliveryNoteController::class, 'send']);
    Route::put('/delivery-notes/{id}/deliver', [DeliveryNoteController::class, 'deliver']);
    Route::post('/delivery-notes/{id}/convert-to-invoice', [DeliveryNoteController::class, 'convertToInvoice']);
    Route::post('/delivery-notes/consolidate-to-invoice', [DeliveryNoteController::class, 'consolidateToInvoice']);
    Route::get('/delivery-notes/{id}/consolidatable', [DeliveryNoteController::class, 'getConsolidatable']);
    Route::get('/delivery-notes/{id}/actions', [DeliveryNoteController::class, 'actions']);
    Route::get('/delivery-notes', [DeliveryNoteController::class, 'index']);
    Route::get('/delivery-notes/{id}', [DeliveryNoteController::class, 'show']);

    // Invoices
    Route::get('/invoices/create', [InvoiceController::class, 'create']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{id}/finalize', [InvoiceController::class, 'finalize']);
    Route::put('/invoices/{id}/send', [InvoiceController::class, 'send']);
    Route::put('/invoices/{id}/mark-overdue', [InvoiceController::class, 'markOverdue']);
    Route::put('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
    Route::post('/invoices/{id}/add-deduction', [InvoiceController::class, 'addDeduction']);
    Route::post('/invoices/{id}/generate-credit-note', [InvoiceController::class, 'generateCreditNote']);
    Route::get('/invoices/{id}/available-deductions', [InvoiceController::class, 'availableDeductions']);
    Route::put('/invoices/{id}/metadata', [InvoiceController::class, 'updateMetadata']);
    Route::put('/invoices/{id}/items', [InvoiceController::class, 'updateItems']);
    Route::get('/invoices/{id}/actions', [InvoiceController::class, 'actions']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    Route::get('/invoices/{id}/ancestor-chain', [InvoiceController::class, 'ancestorChain']);
    Route::get('/invoices/{id}/payments', [PaymentController::class, 'getByInvoice']);
    Route::get('/invoices/{id}/payment-summary', [PaymentController::class, 'getPaymentSummary']);

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/create', [PaymentController::class, 'getCreationData']);
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('{id}', [PaymentController::class, 'show']);
        Route::delete('{id}', [PaymentController::class, 'destroy']);

        // Generic document payment routes
        Route::get('/documents/{documentId}/payments', [PaymentController::class, 'getDocumentPayments']);
        Route::get('/documents/{documentId}/payment-summary', [PaymentController::class, 'getPaymentSummary']);
    });

    // Credit Notes
    Route::get('/credit-notes/create', [CreditNoteController::class, 'create']);
    Route::post('/credit-notes', [CreditNoteController::class, 'store']);
    Route::put('/credit-notes/{id}/finalize', [CreditNoteController::class, 'finalize']);
    Route::put('/credit-notes/{id}/send', [CreditNoteController::class, 'send']);
    Route::put('/credit-notes/{id}/apply', [CreditNoteController::class, 'apply']);
    Route::get('/credit-notes/{id}/actions', [CreditNoteController::class, 'actions']);
    Route::get('/credit-notes', [CreditNoteController::class, 'index']);
    Route::get('/credit-notes/{id}', [CreditNoteController::class, 'show']);

    // Deposits
    Route::get('/deposits/create', [DepositController::class, 'create']);
    Route::get('/deposits/remaining-balance/{id}', [DepositController::class, 'remainingBalance']);
    Route::post('/deposits', [DepositController::class, 'store']);
    Route::put('/deposits/{id}/finalize', [DepositController::class, 'finalize']);
    Route::put('/deposits/{id}/mark-paid', [DepositController::class, 'markPaid']);
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::get('/deposits/{id}', [DepositController::class, 'show']);

    // Balance Invoices
    Route::get('/balance-invoices/create', [BalanceInvoiceController::class, 'create']);
    Route::get('/balance-invoices/balance-data/{quoteId}', [BalanceInvoiceController::class, 'getBalanceData']);
    Route::post('/balance-invoices', [BalanceInvoiceController::class, 'store']);
    Route::put('/balance-invoices/{id}/finalize', [BalanceInvoiceController::class, 'finalize']);
    Route::put('/balance-invoices/{id}/status', [BalanceInvoiceController::class, 'updateStatus']);
    Route::get('/balance-invoices', [BalanceInvoiceController::class, 'index']);
    Route::get('/balance-invoices/{id}', [BalanceInvoiceController::class, 'show']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::get('/products/create', [ProductController::class, 'create']);

    // Companies
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::post('/company-settings', [CompanyController::class, 'update']);
    Route::get('/company-settings', [CompanyController::class, 'show']);
    Route::get('/user/organizations', [CompanyController::class, 'userOrganizations']);
    Route::delete('/organizations/{id}/leave', [CompanyController::class, 'leaveOrganization']);

    // Quotes
    Route::get('/quote/create', [QuoteController::class, 'create']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::put('/quotes/{id}/finalize', [QuoteController::class, 'finalize']);
    Route::put('/quotes/{id}/send', [QuoteController::class, 'send']);
    Route::put('/quotes/{id}/sign', [QuoteController::class, 'sign']);
    Route::put('/quotes/{id}/metadata', [QuoteController::class, 'updateMetadata']);
    Route::put('/quotes/{id}/items', [QuoteController::class, 'updateItems']);
    Route::post('/quotes/{id}/convert-to-invoice', [QuoteController::class, 'convertToInvoice']);
    Route::post('/quotes/{id}/convert-to-purchase-order', [QuoteController::class, 'convertToPurchaseOrder']);
    Route::post('/quotes/{id}/convert-to-proforma', [QuoteController::class, 'convertToProforma']);
    Route::post('/quotes/{id}/create-delivery-note', [QuoteController::class, 'createDeliveryNote']);
    Route::get('/quotes/{id}/actions', [QuoteController::class, 'actions']);
    Route::get('/quotes/{id}/workflow', [QuoteController::class, 'getWorkflowInfo']);
    Route::get('/quotes/{id}', [QuoteController::class, 'show']);
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::put('/quotes/{id}', [QuoteController::class, 'update']);

    // Proformas
    Route::get('/proformas/create', [ProformaController::class, 'create']);
    Route::post('/proformas', [ProformaController::class, 'store']);
    Route::put('/proformas/{id}/finalize', [ProformaController::class, 'finalize']);
    Route::put('/proformas/{id}/send', [ProformaController::class, 'send']);
    Route::put('/proformas/{id}/mark-expired', [ProformaController::class, 'markExpired']);
    Route::put('/proformas/{id}/cancel', [ProformaController::class, 'cancel']);
    Route::post('/proformas/{id}/convert-to-invoice', [ProformaController::class, 'convertToInvoice']);
    Route::get('/proformas/{id}/actions', [ProformaController::class, 'actions']);
    Route::put('/proformas/{id}/metadata', [ProformaController::class, 'updateMetadata']);
    Route::put('/proformas/{id}/items', [ProformaController::class, 'updateItems']);
    Route::get('/proformas', [ProformaController::class, 'index']);
    Route::get('/proformas/{id}', [ProformaController::class, 'show']);
    Route::get('/proformas/{id}/ancestor-chain', [ProformaController::class, 'ancestorChain']);

    // Document Sending
    Route::post('/documents/send', [SendDocumentController::class, 'send']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/tva-report', TvaReportController::class);

    // Users
    Route::get('/company/users', [UserController::class, 'index']);
    Route::post('/company/users/invite', [UserController::class, 'invite']);
    Route::get('/company/users/available', [UserController::class, 'getAvailableUsers']);
    Route::post('/company/users/quick-add', [UserController::class, 'quickAddUser']);
    Route::patch('/company/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::delete('/company/users/{id}', [UserController::class, 'destroy']);

    // Purchase Orders
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::put('/purchase-orders/{id}/finalize', [PurchaseOrderController::class, 'finalize']);
    Route::put('/purchase-orders/{id}/send', [PurchaseOrderController::class, 'send']);
    Route::put('/purchase-orders/{id}/confirm', [PurchaseOrderController::class, 'confirm']);
    Route::put('/purchase-orders/{id}/metadata', [PurchaseOrderController::class, 'updateMetadata']);
    Route::put('/purchase-orders/{id}/items', [PurchaseOrderController::class, 'updateItems']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
    Route::get('/purchase-orders/{id}/ancestor-chain', [PurchaseOrderController::class, 'ancestorChain']);

    // Purchase Invoices
    Route::get('/purchase-invoices/create', [PurchaseInvoiceController::class, 'create']);
    Route::put('/purchase-invoices/{id}/validate', [PurchaseInvoiceController::class, 'validate']);
    Route::put('/purchase-invoices/{id}/mark-paid', [PurchaseInvoiceController::class, 'markPaid']);
    Route::put('/purchase-invoices/{id}/mark-unpaid', [PurchaseInvoiceController::class, 'markUnpaid']);
    Route::put('/purchase-invoices/{id}/cancel', [PurchaseInvoiceController::class, 'cancel']);
    Route::post('/purchase-invoices/analyze', [PurchaseInvoiceController::class, 'analyze'])->name('purchase-invoices.analyze');
    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class);

    // Recurring Invoices
    Route::get('/recurring-invoices', [RecurringInvoiceController::class, 'index']);
    Route::post('/recurring-invoices', [RecurringInvoiceController::class, 'store']);
    Route::get('/recurring-invoices/{id}', [RecurringInvoiceController::class, 'show']);
    Route::put('/recurring-invoices/{id}', [RecurringInvoiceController::class, 'update']);
    Route::delete('/recurring-invoices/{id}', [RecurringInvoiceController::class, 'destroy']);

    // Expenses
    Route::get('/expenses/create', [ExpenseController::class, 'getCreationData']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::post('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::patch('/expenses/{id}/toggle-status', [ExpenseController::class, 'toggleStatus']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

    // Cash Registers
    Route::get('/cash-registers/create', [CashRegisterController::class, 'create']);
    Route::get('/cash-registers/all', [CashRegisterController::class, 'getAll']);
    Route::get('/cash-registers/{id}/dashboard', [CashRegisterController::class, 'dashboard']);
    Route::post('/cash-registers/{id}/open-session', [CashRegisterController::class, 'openSession']);
    Route::post('/cash-registers/{id}/close-session', [CashRegisterController::class, 'closeSession']);
    Route::get('/cash-registers/{id}/sessions', [CashRegisterController::class, 'getSessions']);
    Route::patch('/cash-registers/{id}/toggle-status', [CashRegisterController::class, 'toggleStatus']);
    Route::put('/cash-registers/{id}/set-default', [CashRegisterController::class, 'setDefault']);
    Route::get('/cash-registers/transactions', [CashRegisterController::class, 'transactions']);
    Route::post('/cash-registers/transactions', [CashRegisterController::class, 'storeTransaction']);
    Route::put('/cash-registers/transactions/{id}', [CashRegisterController::class, 'updateTransaction']);
    Route::delete('/cash-registers/transactions/{id}', [CashRegisterController::class, 'deleteTransaction']);
    Route::apiResource('cash-registers', CashRegisterController::class);

    // Bank Remittances
    Route::get('/bank-remittances/create', [BankRemittanceController::class, 'create']);
    Route::get('/bank-remittances/pending-documents', [BankRemittanceController::class, 'pendingDocuments']);
    Route::get('/bank-remittances/{id}/preview', [BankRemittancePreviewController::class, 'show']);
    Route::put('/bank-remittances/{id}/finalize', [BankRemittanceController::class, 'finalize']);
    Route::put('/bank-remittances/{id}/send', [BankRemittanceController::class, 'send']);
    Route::put('/bank-remittances/{id}/deposit', [BankRemittanceController::class, 'markDeposited']);
    Route::put('/bank-remittances/{id}/cancel', [BankRemittanceController::class, 'cancel']);
    Route::get('/bank-remittances/{id}/actions', [BankRemittanceController::class, 'actions']);
    Route::delete('/bank-remittances/{id}/documents/{documentId}', [BankRemittanceController::class, 'removeDocument']);
    Route::apiResource('bank-remittances', BankRemittanceController::class)->except(['destroy']);

    // Payment Documents
    Route::put('/payment-documents/{id}/mark-returned', [PaymentDocumentController::class, 'markReturned']);
    Route::put('/payment-documents/{id}/mark-paid', [PaymentDocumentController::class, 'markPaid']);
    Route::apiResource('payment-documents', PaymentDocumentController::class);

    // Document Theme
    Route::get('/document-theme', [DocumentThemeController::class, 'show']);
    Route::put('/document-theme', [DocumentThemeController::class, 'update']);

    // Documents
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    Route::get('/documents/{id}/preview', [DocumentPreviewController::class, 'show']);

    // Company-scoped routes
    Route::prefix('companies/{company}')->group(function () {
        Route::apiResource('product-categories', ProductCategoryController::class);

        Route::apiResource('payment-conditions', PaymentConditionController::class);
        Route::patch('payment-conditions/{id}/toggle-active', [PaymentConditionController::class, 'toggleActive']);
        Route::put('payment-conditions/{id}/set-default', [PaymentConditionController::class, 'setDefault']);

        Route::apiResource('payment-modes', PaymentModeController::class);
        Route::patch('payment-modes/{id}/toggle-active', [PaymentModeController::class, 'toggleActive']);
        Route::put('payment-modes/{id}/set-default', [PaymentModeController::class, 'setDefault']);

        Route::apiResource('late-fee-interests', LateFeeInterestController::class);
        Route::patch('late-fee-interests/{id}/toggle-active', [LateFeeInterestController::class, 'toggleActive']);
        Route::put('late-fee-interests/{id}/set-default', [LateFeeInterestController::class, 'setDefault']);

        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::patch('bank-accounts/{id}/toggle-active', [BankAccountController::class, 'toggleActive']);
        Route::put('bank-accounts/{id}/set-default', [BankAccountController::class, 'setDefault']);
    });
});
