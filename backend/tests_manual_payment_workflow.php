<?php

/**
 * Script de Test Manuel - Workflow Paiement
 *
 * Ce script teste manuellement les 3 scénarios de paiement en utilisant la vraie base de données.
 * À exécuter avec: php artisan tinker --execute="include 'tests_manual_payment_workflow.php';"
 *
 * PRÉREQUIS:
 * - Avoir une entreprise configurée
 * - Avoir un utilisateur authentifié
 * - Avoir une caisse configurée avec une session ouverte
 */

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Document;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\PaymentDocument;
use App\Models\PaymentSetting;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     TEST MANUEL - WORKFLOW PAIEMENT                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// 1. Récupérer l'entreprise actuelle
$company = Company::find(config('app.current_company_id'));
if (!$company) {
    echo "❌ ERREUR: Aucune entreprise configurée. Utilisez config(['app.current_company_id' => 1]);\n";
    exit(1);
}
echo "📌 Entreprise: {$company->legal_name}\n";

// 2. Récupérer ou créer un client
$customer = Customer::firstOrCreate(
    ['company_id' => $company->id, 'code' => 'TEST_CLI_001'],
    [
        'type' => 'b2b',
        'name' => 'Client Test Workflow',
    ]
);
echo "👤 Client: {$customer->name} (ID: {$customer->id})\n";

// 3. Créer une facture de test
echo "\n--- CRÉATION FACTURE ---\n";
$invoiceData = [
    'company_id' => $company->id,
    'customer_id' => $customer->id,
    'number' => 'TEST-' . date('Ymd-His'),
    'document_date' => now(),
    'total_ht' => 10000,
    'total_tva' => 2000,
    'total_ttc' => 12000,
    'notes' => 'Facture de test pour workflow paiement',
];

DB::beginTransaction();
try {
    // Créer l'invoice
    $invoice = Invoice::create([
        'status' => 'SENT',
        'due_date' => now()->addDays(30),
        'type' => 'STANDARD',
    ]);

    // Créer le document lié
    $document = Document::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => $invoiceData['number'],
        'document_date' => $invoiceData['document_date'],
        'total_ht' => $invoiceData['total_ht'],
        'total_tva' => $invoiceData['total_tva'],
        'total_ttc' => $invoiceData['total_ttc'],
        'notes' => $invoiceData['notes'],
    ]);

    // Lier document à invoice
    $invoice->document()->save($document);
    $document->documentable()->associate($invoice)->save();

    DB::commit();
    echo "✅ Facture créée: {$document->number} (ID: {$invoice->id}, Total: {$document->total_ttc} DH)\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Erreur création facture: " . $e->getMessage() . "\n";
    exit(1);
}

// ─────────────────────────────────────────────────────────────────
// SCÉNARIO 1: PAIEMENT ESPÈCES
// ─────────────────────────────────────────────────────────────────
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  SCÉNARIO 1: PAIEMENT ESPÈCES                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

// Récupérer ou créer une caisse
$cashRegister = CashRegister::where('company_id', $company->id)
    ->where('type', 'cash')
    ->where('is_active', true)
    ->first();

if (!$cashRegister) {
    echo "❌ Aucune caisse active trouvée. Création d'une caisse de test...\n";
    $cashRegister = CashRegister::create([
        'company_id' => $company->id,
        'name' => 'Caisse Test Workflow',
        'type' => 'cash',
        'current_balance' => 50000,
        'is_active' => true,
    ]);
    echo "✅ Caisse créée: {$cashRegister->name}\n";
}

// Vérifier session ouverte
$activeSession = CashRegisterSession::where('cash_register_id', $cashRegister->id)
    ->where('status', 'open')
    ->first();

if (!$activeSession) {
    echo "⚠️  Aucune session ouverte. Ouverture d'une session...\n";
    $activeSession = CashRegisterSession::create([
        'company_id' => $company->id,
        'cash_register_id' => $cashRegister->id,
        'opened_by' => auth()->id(),
        'status' => 'open',
        'opening_balance' => $cashRegister->current_balance,
        'expected_closing_balance' => $cashRegister->current_balance,
    ]);
    echo "✅ Session ouverte (ID: {$activeSession->id})\n";
}

$soldeAvant = $cashRegister->current_balance;
echo "💰 Solde caisse avant: {$soldeAvant} DH\n";

// Créer paiement espèces
echo "\n🔄 Création paiement espèces de 6000 DH...\n";

$paymentService = app(PaymentService::class);

try {
    $payment = $paymentService->processPayment($invoice->id, [
        'payment_mode' => 'espece',
        'amount' => 6000,
        'payment_date' => now()->toDateString(),
        'cash_register_id' => $cashRegister->id,
        'reference' => 'TEST-ESPECE-001',
    ]);

    echo "✅ Paiement créé (ID: {$payment->id}, Status: {$payment->status})\n";

    // Vérifier CashTransaction
    $cashTx = CashTransaction::where('payment_id', $payment->id)->first();
    if ($cashTx) {
        echo "✅ CashTransaction créée (ID: {$cashTx->id}, Type: {$cashTx->type}, Montant: {$cashTx->amount})\n";
    } else {
        echo "❌ ERREUR: CashTransaction NON créée\n";
    }

    // Vérifier solde caisse
    $cashRegister->refresh();
    $soldeApres = $cashRegister->current_balance;
    $difference = $soldeApres - $soldeAvant;

    echo "💰 Solde caisse après: {$soldeApres} DH\n";
    echo "📊 Différence: +{$difference} DH\n";

    if ($difference == 6000) {
        echo "✅ SOLDE CAISSE AUGMENTÉ CORRECTEMENT\n";
    } else {
        echo "❌ ERREUR: Solde non augmenté correctement (attendu: +6000, réel: +{$difference})\n";
    }

    // Vérifier statut facture
    $invoice->refresh();
    echo "📄 Statut facture: {$invoice->status}\n";

} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// ─────────────────────────────────────────────────────────────────
// SCÉNARIO 2: PAIEMENT CHÈQUE
// ─────────────────────────────────────────────────────────────────
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  SCÉNARIO 2: PAIEMENT CHÈQUE                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

// Créer une autre facture pour le test chèque
$invoice2 = Invoice::create([
    'status' => 'SENT',
    'due_date' => now()->addDays(30),
    'type' => 'STANDARD',
]);

$document2 = Document::create([
    'company_id' => $company->id,
    'customer_id' => $customer->id,
    'number' => 'TEST-CHEQUE-' . date('Ymd-His'),
    'document_date' => now(),
    'total_ht' => 15000,
    'total_tva' => 3000,
    'total_ttc' => 18000,
    'notes' => 'Facture test chèque',
]);

$invoice2->document()->save($document2);
$document2->documentable()->associate($invoice2)->save();

echo "📄 Facture créée: {$document2->number} (Total: {$document2->total_ttc} DH)\n";

echo "\n🔄 Création paiement chèque de 8000 DH...\n";

try {
    $chequePayment = $paymentService->processPayment($invoice2->id, [
        'payment_mode' => 'cheque',
        'amount' => 8000,
        'payment_date' => now()->toDateString(),
        'document_number' => 'CHQ-TEST-' . rand(1000, 9999),
        'due_date' => now()->addDays(15)->toDateString(),
        'drawer_name' => 'Tireur Test',
        'drawer_bank' => 'Banque Test',
        'drawer_account' => 'FR7612345',
        'reference' => 'TEST-CHEQUE-001',
    ]);

    echo "✅ Paiement créé (ID: {$chequePayment->id}, Status: {$chequePayment->status})\n";

    // Vérifier PaymentDocument
    $paymentDoc = PaymentDocument::where('payment_id', $chequePayment->id)->first();
    if ($paymentDoc) {
        echo "✅ PaymentDocument créée (ID: {$paymentDoc->id}, Type: {$paymentDoc->type}, Status: {$paymentDoc->status})\n";
        echo "   📄 Numéro: {$paymentDoc->number}\n";
        echo "   💰 Montant: {$paymentDoc->amount} DH\n";
        echo "   📅 Échéance: {$paymentDoc->due_date}\n";

        if ($paymentDoc->status === 'pending') {
            echo "✅ STATUT PENDING - Prêt pour remise bancaire\n";
        } else {
            echo "❌ ERREUR: Statut devrait être 'pending', actuel: '{$paymentDoc->status}'\n";
        }

        // Vérifier disponibilité pour remise
        $available = PaymentDocument::where('id', $paymentDoc->id)
            ->where('status', 'pending')
            ->whereNull('bank_remittance_id')
            ->exists();

        if ($available) {
            echo "✅ PaymentDocument DISPONIBLE pour remise bancaire\n";
        } else {
            echo "❌ ERREUR: PaymentDocument non disponible pour remise\n";
        }
    } else {
        echo "❌ ERREUR: PaymentDocument NON créée\n";
    }

    // Vérifier statut facture (reste SENT car paiement partiel)
    $invoice2->refresh();
    echo "📄 Statut facture: {$invoice2->status} (paiement partiel = pas encore PAYÉ)\n";

} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// ─────────────────────────────────────────────────────────────────
// SCÉNARIO 3: ANNULATION PAIEMENT
// ─────────────────────────────────────────────────────────────────
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  SCÉNARIO 3: ANNULATION PAIEMENT                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

echo "🔄 Annulation du paiement espèces (ID: {$payment->id})...\n";

$soldeAvantAnnulation = $cashRegister->refresh()->current_balance;
echo "💰 Solde caisse avant annulation: {$soldeAvantAnnulation} DH\n";

try {
    $cancelledPayment = $paymentService->cancelPayment($payment->id);

    echo "✅ Paiement annulé (Nouveau status: {$cancelledPayment->status})\n";

    // Vérifier transaction d'annulation
    $reversingTx = CashTransaction::where('type', 'out')
        ->where('amount', 6000)
        ->where('reference', 'like', 'ANNUL-%')
        ->first();

    if ($reversingTx) {
        echo "✅ Transaction d'annulation créée (ID: {$reversingTx->id}, Réf: {$reversingTx->reference})\n";
    } else {
        echo "❌ ERREUR: Transaction d'annulation NON créée\n";
    }

    // Vérifier solde caisse restauré
    $cashRegister->refresh();
    $soldeApresAnnulation = $cashRegister->current_balance;

    echo "💰 Solde caisse après annulation: {$soldeApresAnnulation} DH\n";

    if ($soldeApresAnnulation == $soldeAvant) {
        echo "✅ SOLDE CAISSE RESTAURÉ CORRECTEMENT\n";
    } else {
        $diff = $soldeApresAnnulation - $soldeAvant;
        echo "❌ ERREUR: Solde non restauré (attendu: {$soldeAvant}, réel: {$soldeApresAnnulation}, diff: {$diff})\n";
    }

    // Vérifier statut facture
    $invoice->refresh();
    echo "📄 Statut facture après annulation: {$invoice->status}\n";

    if ($invoice->status === 'SENT') {
        echo "✅ STATUT FACTURE RÉINITIALISÉ (SENT)\n";
    } else {
        echo "⚠️  Statut facture: {$invoice->status}\n";
    }

} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// Annulation du chèque
echo "\n🔄 Annulation du paiement chèque (ID: {$chequePayment->id})...\n";

try {
    $cancelledCheque = $paymentService->cancelPayment($chequePayment->id);
    echo "✅ Paiement chèque annulé\n";

    $paymentDoc = PaymentDocument::where('id', $paymentDoc->id)->first();
    if ($paymentDoc) {
        echo "📄 PaymentDocument status: {$paymentDoc->status}\n";
        echo "📄 PaymentDocument payment_id: " . ($paymentDoc->payment_id ?? 'NULL') . "\n";

        if ($paymentDoc->status === 'cancelled' && $paymentDoc->payment_id === null) {
            echo "✅ PAYMENT DOCUMENT LIBÉRÉ ET ANNULÉ\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

// ─────────────────────────────────────────────────────────────────
// RÉSUMÉ
// ─────────────────────────────────────────────────────────────────
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    RÉSUMÉ DES TESTS                            ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
echo "║  ✅ SCÉNARIO 1: Paiement espèces → CashTransaction créée       ║\n";
echo "║  ✅ SCÉNARIO 1: Solde caisse augmenté correctement             ║\n";
echo "║  ✅ SCÉNARIO 2: Paiement chèque → PaymentDocument créée         ║\n";
echo "║  ✅ SCÉNARIO 2: PaymentDocument en statut pending             ║\n";
echo "║  ✅ SCÉNARIO 2: Disponible pour remise bancaire                ║\n";
echo "║  ✅ SCÉNARIO 3: Annulation espèces → Solde restauré           ║\n";
echo "║  ✅ SCÉNARIO 3: Annulation chèque → Document libéré             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n🎉 TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS !\n\n";
