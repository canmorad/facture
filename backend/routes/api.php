<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Auth\UserStatusController;
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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\DocumentPreviewController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SendDocumentController;
use App\Http\Controllers\TvaReportController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/invitations/verify/{token}', [InvitationController::class, 'verify']);
Route::post('/invitations/accept', [InvitationController::class, 'accept']);

Route::middleware('auth:sanctum')->get('/user-status', UserStatusController::class);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->get('/user-companies', function (Request $request) {
    $user = $request->user();
    $companies = $user->companies()->get();
    return response()->json($companies);
});

Route::middleware(['auth:sanctum', 'check.company'])->group(function () {
    Route::apiResource('tax-rates', TaxRateController::class)->except(['destroy']);
    Route::delete('tax-rates/{id}', [TaxRateController::class, 'destroy']);
    Route::patch('tax-rates/{id}/toggle', [TaxRateController::class, 'toggleStatus']);

    Route::get('/document-settings/{type}', [DocumentSettingController::class, 'show']);
    Route::post('/document-settings', [DocumentSettingController::class, 'storeOrUpdate']);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);

    Route::get('/numbering-serie', [NumberingSerieController::class, 'show']);
    Route::post('/numbering-serie', [NumberingSerieController::class, 'store']);
    Route::put('/numbering-serie', [NumberingSerieController::class, 'update']);

    Route::get('/delivery-notes/create', [DeliveryNoteController::class, 'create']);
    Route::post('/delivery-notes', [DeliveryNoteController::class, 'store']);
    Route::put('/delivery-notes/{id}/finalize', [DeliveryNoteController::class, 'finalize']);
    Route::put('/delivery-notes/{id}/send', [DeliveryNoteController::class, 'send']);
    Route::put('/delivery-notes/{id}/deliver', [DeliveryNoteController::class, 'deliver']);
    Route::post('/delivery-notes/{id}/convert-to-invoice', [DeliveryNoteController::class, 'convertToInvoice']);
    Route::get('/delivery-notes/{id}/actions', [DeliveryNoteController::class, 'actions']);
    Route::get('/delivery-notes', [DeliveryNoteController::class, 'index']);
    Route::get('/delivery-notes/{id}', [DeliveryNoteController::class, 'show']);

    Route::get('/invoices/create', [InvoiceController::class, 'create']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{id}/finalize', [InvoiceController::class, 'finalize']);
    Route::put('/invoices/{id}/send', [InvoiceController::class, 'send']);
    Route::put('/invoices/{id}/mark-paid', [InvoiceController::class, 'markPaid']);
    Route::put('/invoices/{id}/mark-overdue', [InvoiceController::class, 'markOverdue']);
    Route::put('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
    Route::post('/invoices/{id}/add-deduction', [InvoiceController::class, 'addDeduction']);
    Route::get('/invoices/{id}/available-deductions', [InvoiceController::class, 'availableDeductions']);
    Route::put('/invoices/{id}/metadata', [InvoiceController::class, 'updateMetadata']);
    Route::put('/invoices/{id}/items', [InvoiceController::class, 'updateItems']);
    Route::get('/invoices/{id}/actions', [InvoiceController::class, 'actions']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{id}/ancestor-chain', [InvoiceController::class, 'ancestorChain']);

    Route::get('/deposits/create', [DepositController::class, 'create']);
    Route::get('/deposits/remaining-balance/{id}', [DepositController::class, 'remainingBalance']);
    Route::post('/deposits', [DepositController::class, 'store']);
    Route::put('/deposits/{id}/finalize', [DepositController::class, 'finalize']);
    Route::put('/deposits/{id}/mark-paid', [DepositController::class, 'markPaid']);
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::get('/deposits/{id}', [DepositController::class, 'show']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::get('/products/create', [ProductController::class, 'create']);

    Route::post('/companies', [CompanyController::class, 'store']);
    Route::post('/company-settings', [CompanyController::class, 'update']);
    Route::get('/company-settings', [CompanyController::class, 'show']);
    Route::get('/user/organizations', [CompanyController::class, 'userOrganizations']);
    Route::delete('/organizations/{id}/leave', [CompanyController::class, 'leaveOrganization']);

    Route::get('/quote/create', [QuoteController::class, 'create']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::put('/quotes/{id}/finalize', [QuoteController::class, 'finalize']);
    Route::put('/quotes/{id}/send', [QuoteController::class, 'send']);
    Route::put('/quotes/{id}/sign', [QuoteController::class, 'sign']);
    Route::put('/quotes/{id}/metadata', [QuoteController::class, 'updateMetadata']);
    Route::post('/quotes/{id}/convert-to-invoice', [QuoteController::class, 'convertToInvoice']);
    Route::post('/quotes/{id}/convert-to-purchase-order', [QuoteController::class, 'convertToPurchaseOrder']);
    Route::post('/quotes/{id}/create-delivery-note', [QuoteController::class, 'createDeliveryNote']);
    Route::get('/quotes/{id}/actions', [QuoteController::class, 'actions']);
    Route::get('/quotes/{id}', [QuoteController::class, 'show']);
    Route::get('/quotes', [QuoteController::class, 'index']);

    Route::post('/documents/send', [SendDocumentController::class, 'send']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/tva-report', TvaReportController::class);

    Route::get('/company/users', [UserController::class, 'index']);
    Route::post('/company/users/invite', [UserController::class, 'invite']);
    Route::patch('/company/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::delete('/company/users/{id}', [UserController::class, 'destroy']);

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

    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class);

    Route::get('/recurring-invoices', [RecurringInvoiceController::class, 'index']);
    Route::post('/recurring-invoices', [RecurringInvoiceController::class, 'store']);
    Route::get('/recurring-invoices/{id}', [RecurringInvoiceController::class, 'show']);
    Route::put('/recurring-invoices/{id}', [RecurringInvoiceController::class, 'update']);
    Route::delete('/recurring-invoices/{id}', [RecurringInvoiceController::class, 'destroy']);

    Route::get('/expenses/create', [ExpenseController::class, 'getCreationData']);
    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::post('/expenses', [ExpenseController::class, 'store']);
    Route::post('/expenses/{id}', [ExpenseController::class, 'update']);
    Route::patch('/expenses/{id}/toggle-status', [ExpenseController::class, 'toggleStatus']);
    Route::delete('/expenses/{id}', [ExpenseController::class, 'destroy']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

    Route::get('/document-theme', [DocumentThemeController::class, 'show']);
    Route::put('/document-theme', [DocumentThemeController::class, 'update']);

    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
    Route::get('/documents/{id}/preview', [DocumentPreviewController::class, 'show']);

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