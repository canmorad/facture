<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CompanyController;

use App\Http\Controllers\Auth\UserStatusController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\NumberingSerieController;
use App\Http\Controllers\CustomerController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::middleware(['auth:sanctum', 'has.company'])->group(function () {
// });

Route::middleware('auth:sanctum')->get('/user-status', UserStatusController::class);

Route::middleware('auth:sanctum')->get('/user-companies', function (Request $request) {
    $user = $request->user();
    $companies = $user->companies()->get();
    return response()->json($companies);
});

Route::middleware('auth:sanctum')->group(function () {

    // Route::get('/company/users', [UserController::class, 'index']);
    // Route::post('/company/invitations', [UserController::class, 'invite']);
    // Route::patch('/company/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    // Route::delete('/company/users/{id}', [UserController::class, 'destroy']);

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
    Route::get('/delivery-notes', [DeliveryNoteController::class, 'index']);

    Route::get('/invoices/create', [InvoiceController::class, 'create']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::put('/invoices/{id}/finalize', [InvoiceController::class, 'finalize']);
    Route::get('/invoices', [InvoiceController::class, 'index']);

   Route::get('/deposits/create', [DepositController::class, 'create']);
    Route::get('/deposits/remaining-balance/{id}', [DepositController::class, 'remainingBalance']);
    Route::post('/deposits', [DepositController::class, 'store']);
    Route::put('/deposits/{id}/finalize', [DepositController::class, 'finalize']);
    Route::get('/deposits', [DepositController::class, 'index']);


    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::get('/products/create', [ProductController::class, 'create']);

     Route::post('/companies', [CompanyController::class, 'store']);
    Route::post('/company-settings', [CompanyController::class, 'update']);
    Route::get('/company-settings', [CompanyController::class, 'show']);
    Route::get('/user/organizations', [CompanyController::class, 'userOrganizations']);
    Route::delete('/organizations/{id}/leave', [CompanyController::class, 'leaveOrganization']);;

    // Route::get('/invoices', [InvoiceController::class, 'index']);
    // Route::post('/invoices', [InvoiceController::class, 'store']);
    // Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    // Route::put('/invoices/{id}', [InvoiceController::class, 'update']);
    // Route::patch('/invoices/{id}/template', [InvoiceController::class, 'updateTemplate']);
    // Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
    // Route::put('/invoices/{id}/convert', [InvoiceController::class, 'convertToInvoice']);

    // Route::apiResource('fournisseurs', FournisseursController::class);

    // Route::get('/charges', [ChargeController::class, 'index']);
    // Route::post('/charges', [ChargeController::class, 'store']);
    // Route::get('/charges/{id}', [ChargeController::class, 'show']);
    // Route::put('/charges/{id}', [ChargeController::class, 'update']);
    // Route::delete('/charges/{id}', [ChargeController::class, 'destroy']);

     Route::get('/quote/create', [QuoteController::class, 'create']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::put('/quotes/{id}/finalize', [QuoteController::class, 'finalize']);
    Route::get('/quotes/{id}', [QuoteController::class, 'show']);
    

    Route::get('/dashboard/activities', [DashboardController::class, 'getActivities']);
    Route::get('/quotes', [QuoteController::class, 'index']);

    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::put('/purchase-orders/{id}/finalize', [PurchaseOrderController::class, 'finalize']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);


    Route::apiResource('purchase-invoices', PurchaseInvoiceController::class);

    Route::get('/document-theme', [DocumentThemeController::class, 'show']);
    Route::put('/document-theme', [DocumentThemeController::class, 'update']);


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