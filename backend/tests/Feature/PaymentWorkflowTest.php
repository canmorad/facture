<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Document;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\PaymentDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    protected Company $company;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Get or create company (using existing data from migrations)
        $this->company = Company::first() ?? Company::create([
            'company_name' => 'Test Company',
            'rc' => '123456',
            'ice' => 'ICE001',
            'if' => 'IF001',
            'address' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '12345',
            'country' => 'Maroc',
            'phone' => '123456789',
            'email' => 'test@test.com',
        ]);

        // Get or create user
        $this->user = User::first() ?? User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        // Attach user to company with role
        if (!$this->user->companies()->where('companies.id', $this->company->id)->exists()) {
            // Create owner role if not exists
            $ownerRole = \App\Models\Role::firstOrCreate(
                ['name' => 'owner'],
                ['description' => 'Owner role with full access']
            );
            $this->user->companies()->attach($this->company->id, ['role_id' => $ownerRole->id]);
        }

        // Generate Sanctum token
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Set current company
        config(['app.current_company_id' => $this->company->id]);

        // Bypass authorization gates for testing
        \Gate::before(function () {
            return true;
        });
    }

    /**
     * Helper: Create a B2B customer with proper polymorphic relationship
     */
    protected function createB2BCustomer(string $legalName, string $ice): Customer
    {
        $b2bCustomer = \App\Models\B2bCustomer::create([
            'legal_name' => $legalName,
            'ice' => $ice,
            'rc' => 'RC_' . str_replace(' ', '_', $legalName),
        ]);

        return Customer::create([
            'company_id' => $this->company->id,
            'type' => 'b2b',
            'customerable_type' => \App\Models\B2bCustomer::class,
            'customerable_id' => $b2bCustomer->id,
        ]);
    }

    /**
     * Test Scenario 1: Cash Payment (Espèces)
     */
    public function test_cash_payment_workflow(): void
    {
        Sanctum::actingAs($this->user, ['*'], 'sanctum');
        echo "\n=== TEST 1: PAIEMENT ESPÈCES ===\n";

        // 1. Create b2b customer
        $customer = $this->createB2BCustomer('Customer Test', 'ICE_TEST_001');

        // 2. Create invoice document (manually to avoid model issues)
        $invoiceId = DB::table('invoices')->insertGetId([
            'status' => 'SENT',
            'due_date' => now()->addDays(30),
            'type' => 'STANDARD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentId = DB::table('documents')->insertGetId([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'number' => 'INV-TEST-001',
            'total_ht' => 1000,
            'total_tva' => 200,
            'total_ttc' => 1200,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoiceId,
            'notes' => 'Test invoice',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "  ✓ Facture créée (ID: {$invoiceId}, Total: 1200 DH)\n";

        // 3. Create cash register and session
        $cashRegister = CashRegister::create([
            'company_id' => $this->company->id,
            'name' => 'Caisse Test',
            'code' => 'CAISSE_TEST_001',
            'type' => 'cash',
            'current_balance' => 5000,
            'is_active' => true,
        ]);

        $session = CashRegisterSession::create([
            'company_id' => $this->company->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by_user_id' => $this->user->id,
            'status' => 'open',
            'opening_balance' => 5000,
            'expected_closing_balance' => 5000,
            'opened_at' => now(),
        ]);

        echo "  ✓ Caisse créée (Solde initial: 5000 DH)\n";

        // Debug: Check auth
        echo "  DEBUG Auth::id(): " . (\Illuminate\Support\Facades\Auth::id() ?? 'null') . "\n";
        echo "  DEBUG user->id: {$this->user->id}\n";

        // 4. Create cash payment via API
        $response = $this->postJson('/api/payments', [
                'invoice_id' => $invoiceId,
                'payment_mode' => 'espece',
                'amount' => 1200,
                'payment_date' => now()->toDateString(),
                'cash_register_id' => $cashRegister->id,
            ]);

        if ($response->status() === 201) {
            echo "  ✓ Paiement créé (Status: {$response->json('status')})\n";
        } else {
            echo "  ✗ Erreur création paiement (HTTP {$response->status()})\n";
            echo "    Message: " . $response->json('message', 'Unknown') . "\n";
            echo "    Body: " . json_encode($response->json()) . "\n";
            $this->assertTrue(false, 'Payment creation failed: ' . $response->json('message', 'Unknown'));
        }

        // 5. Verify payment created
        $payment = Payment::where('invoice_id', $invoiceId)->first();
        $this->assertNotNull($payment, 'Payment should exist');
        echo "  ✓ Payment créé (ID: {$payment->id}, Status: {$payment->status})\n";

        // 6. Verify CashTransaction created
        $cashTransaction = CashTransaction::where('payment_id', $payment->id)->first();
        $this->assertNotNull($cashTransaction, 'CashTransaction should exist');
        $this->assertEquals('in', $cashTransaction->type);
        $this->assertEquals(1200, $cashTransaction->amount);
        echo "  ✓ CashTransaction créée (Type: {$cashTransaction->type}, Montant: {$cashTransaction->amount})\n";

        // 7. Verify cash register balance increased
        $cashRegister->refresh();
        $this->assertEquals(6200, $cashRegister->current_balance);
        echo "  ✓ Solde caisse augmenté (Nouveau solde: {$cashRegister->current_balance} DH)\n";

        // 8. Verify session balance updated
        $session->refresh();
        $this->assertEquals(6200, $session->expected_closing_balance);
        echo "  ✓ Balance session mise à jour (Attendue: {$session->expected_closing_balance} DH)\n";

        // 9. Verify invoice marked as paid
        $invoiceStatus = DB::table('invoices')->where('id', $invoiceId)->value('status');
        $this->assertEquals('PAID', $invoiceStatus);
        echo "  ✓ Facture marquée comme PAYÉ\n";

        echo "✅ TEST 1 RÉUSSI: Paiement espèces fonctionne correctement\n\n";
    }

    /**
     * Test Scenario 2: Cheque Payment
     */
    public function test_cheque_payment_workflow(): void
    {
        Sanctum::actingAs($this->user, ['*'], 'sanctum');
        echo "\n=== TEST 2: PAIEMENT CHÈQUE ===\n";

        // 1. Create customer
        $customer = $this->createB2BCustomer('Customer Chèque', 'ICE_CLI_002');

        // 2. Create invoice
        $invoiceId = DB::table('invoices')->insertGetId([
            'status' => 'SENT',
            'due_date' => now()->addDays(30),
            'type' => 'STANDARD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentId = DB::table('documents')->insertGetId([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'number' => 'INV-CHQ-002',
            'total_ht' => 5000,
            'total_tva' => 1000,
            'total_ttc' => 6000,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoiceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "  ✓ Facture créée (ID: {$invoiceId}, Total: 6000 DH)\n";

        // 3. Create cheque payment
        $response = $this->postJson('/api/payments', [
                'invoice_id' => $invoiceId,
                'payment_mode' => 'cheque',
                'amount' => 3000,
                'payment_date' => now()->toDateString(),
                'document_number' => 'CHQ-99999',
                'due_date' => now()->addDays(15)->toDateString(),
                'drawer_name' => 'Client Test',
                'drawer_bank' => 'Banque Test',
            ]);

        if ($response->status() === 201) {
            echo "  ✓ Paiement créé (Status: {$response->json('status')})\n";
        } else {
            echo "  ✗ Erreur: " . $response->json('message', 'Unknown') . "\n";
            $this->assertTrue(false, 'Cheque payment creation failed');
        }

        // 4. Verify payment created
        $payment = Payment::where('invoice_id', $invoiceId)->first();
        $this->assertNotNull($payment, 'Payment should exist');
        $this->assertEquals('cheque', $payment->payment_mode);
        echo "  ✓ Payment créé (Mode: {$payment->payment_mode})\n";

        // 5. Verify PaymentDocument created
        $paymentDocument = PaymentDocument::where('payment_id', $payment->id)->first();
        $this->assertNotNull($paymentDocument, 'PaymentDocument should exist');
        $this->assertEquals('cheque', $paymentDocument->type);
        $this->assertEquals('pending', $paymentDocument->status);
        $this->assertEquals('CHQ-99999', $paymentDocument->number);
        $this->assertEquals(3000, $paymentDocument->amount);
        echo "  ✓ PaymentDocument créé (Type: {$paymentDocument->type}, Status: {$paymentDocument->status})\n";

        // 6. Verify available for remittance
        $availableForRemittance = PaymentDocument::where('id', $paymentDocument->id)
            ->where('status', 'pending')
            ->whereNull('bank_remittance_id')
            ->exists();
        $this->assertTrue($availableForRemittance);
        echo "  ✓ PaymentDocument disponible pour remise bancaire\n";

        // 7. Verify invoice NOT fully paid (partial payment)
        $invoiceStatus = DB::table('invoices')->where('id', $invoiceId)->value('status');
        $this->assertEquals('SENT', $invoiceStatus);
        echo "  ✓ Facture NON marquée payée (paiement partiel)\n";

        echo "✅ TEST 2 RÉUSSI: Paiement chèque fonctionne correctement\n\n";
    }

    /**
     * Test Scenario 3: Payment Cancellation
     */
    public function test_payment_cancellation_workflow(): void
    {
        Sanctum::actingAs($this->user, ['*'], 'sanctum');
        echo "\n=== TEST 3: ANNULATION PAIEMENT ===\n";

        // 1. Setup: Create a cash payment
        $customer = $this->createB2BCustomer('Customer Annulation', 'ICE_CLI_003');

        $invoiceId = DB::table('invoices')->insertGetId([
            'status' => 'SENT',
            'due_date' => now()->addDays(30),
            'type' => 'STANDARD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insertGetId([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'number' => 'INV-ANNUL-003',
            'total_ht' => 2000,
            'total_tva' => 400,
            'total_ttc' => 2400,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoiceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cashRegister = CashRegister::create([
            'company_id' => $this->company->id,
            'name' => 'Caisse Annulation',
            'code' => 'CAISSE_ANNUL_001',
            'type' => 'cash',
            'current_balance' => 10000,
            'is_active' => true,
        ]);

        $session = CashRegisterSession::create([
            'company_id' => $this->company->id,
            'cash_register_id' => $cashRegister->id,
            'opened_by_user_id' => $this->user->id,
            'status' => 'open',
            'opening_balance' => 10000,
            'expected_closing_balance' => 10000,
            'opened_at' => now(),
        ]);

        echo "  ✓ Setup créé (Caisse solde: 10000 DH)\n";

        // 2. Create payment
        $paymentResponse = $this->postJson('/api/payments', [
                'invoice_id' => $invoiceId,
                'payment_mode' => 'espece',
                'amount' => 2400,
                'payment_date' => now()->toDateString(),
                'cash_register_id' => $cashRegister->id,
            ]);

        $payment = Payment::where('invoice_id', $invoiceId)->first();
        $this->assertNotNull($payment);
        echo "  ✓ Paiement créé (ID: {$payment->id})\n";

        // Record state before cancellation
        $balanceBefore = $cashRegister->refresh()->current_balance;
        echo "  ✓ Solde caisse avant annulation: {$balanceBefore} DH\n";

        // 3. Cancel payment
        $cancelResponse = $this->deleteJson("/api/payments/{$payment->id}");

        if ($cancelResponse->status() === 200) {
            echo "  ✓ Paiement annulé\n";
        } else {
            echo "  ✗ Erreur annulation: " . $cancelResponse->json('message', 'Unknown') . "\n";
        }

        // 4. Verify payment cancelled
        $payment->refresh();
        $this->assertEquals('cancelled', $payment->status);
        echo "  ✓ Payment status: CANCELLED\n";

        // 5. Verify reversing transaction created
        $reversingTx = CashTransaction::where('type', 'out')
            ->where('amount', 2400)
            ->where('reference', 'like', 'ANNUL-%')
            ->first();
        $this->assertNotNull($reversingTx);
        echo "  ✓ Transaction d'annulation créée\n";

        // 6. Verify balance restored
        $cashRegister->refresh();
        $this->assertEquals(10000, $cashRegister->current_balance);
        echo "  ✓ Solde caisse restauré: {$cashRegister->current_balance} DH\n";

        // 7. Verify invoice status reverted
        $invoiceStatus = DB::table('invoices')->where('id', $invoiceId)->value('status');
        $this->assertEquals('SENT', $invoiceStatus);
        echo "  ✓ Statut facture réinitialisé: SENT\n";

        // 8. Test cheque payment cancellation
        $customer2 = $this->createB2BCustomer('Customer Chèque Annul', 'ICE_CLI_004');

        $invoiceId2 = DB::table('invoices')->insertGetId([
            'status' => 'SENT',
            'due_date' => now()->addDays(30),
            'type' => 'STANDARD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insertGetId([
            'company_id' => $this->company->id,
            'customer_id' => $customer2->id,
            'number' => 'INV-CHQ-ANNUL',
            'total_ht' => 1500,
            'total_tva' => 300,
            'total_ttc' => 1800,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoiceId2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $chequeResponse = $this->postJson('/api/payments', [
                'invoice_id' => $invoiceId2,
                'payment_mode' => 'cheque',
                'amount' => 1800,
                'payment_date' => now()->toDateString(),
                'document_number' => 'CHQ-ANNUL-001',
                'due_date' => now()->addDays(15)->toDateString(),
            ]);

        $chequePayment = Payment::where('invoice_id', $invoiceId2)->first();
        $this->assertNotNull($chequePayment);

        // Cancel cheque payment
        $cancelChequeResponse = $this->deleteJson("/api/payments/{$chequePayment->id}");

        $this->assertEquals(200, $cancelChequeResponse->status());

        // Verify PaymentDocument released
        $paymentDoc = PaymentDocument::where('payment_id', $chequePayment->id)->first();
        $this->assertEquals('cancelled', $paymentDoc->status);
        $this->assertNull($paymentDoc->payment_id);
        echo "  ✓ Chèque annulé et PaymentDocument libéré\n";

        echo "✅ TEST 3 RÉUSSI: Annulation fonctionne correctement\n\n";
    }

    /**
     * Test partial payment
     */
    public function test_partial_payment_workflow(): void
    {
        Sanctum::actingAs($this->user, ['*'], 'sanctum');
        echo "\n=== TEST 4: PAIEMENT PARTIEL ===\n";

        $customer = $this->createB2BCustomer('Customer Partiel', 'ICE_CLI_005');

        $invoiceId = DB::table('invoices')->insertGetId([
            'status' => 'SENT',
            'due_date' => now()->addDays(30),
            'type' => 'STANDARD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documents')->insertGetId([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'number' => 'INV-PARTIAL',
            'total_ht' => 10000,
            'total_tva' => 2000,
            'total_ttc' => 12000,
            'documentable_type' => Invoice::class,
            'documentable_id' => $invoiceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Partial payment (50%)
        $response = $this->postJson('/api/payments', [
                'invoice_id' => $invoiceId,
                'payment_mode' => 'virement',
                'amount' => 6000,
                'payment_date' => now()->toDateString(),
            ]);

        $this->assertEquals(201, $response->status());

        // Verify invoice NOT marked as paid
        $invoiceStatus = DB::table('invoices')->where('id', $invoiceId)->value('status');
        $this->assertEquals('SENT', $invoiceStatus);
        echo "  ✓ Paiement partiel: Facture reste en statut SENT\n";

        echo "✅ TEST 4 RÉUSSI: Paiement partiel géré correctement\n\n";
    }
}
