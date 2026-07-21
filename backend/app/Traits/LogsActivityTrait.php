<?php

namespace App\Traits;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsActivityTrait
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $userName = auth()->user()?->name ?? 'Système';
        $modelName = class_basename($this);
        $identifier = $this->number ?? $this->name ?? $this->title ?? $this->company_name ?? $this->label ?? $this->libelle ?? $this->email ?? $this->id;

        // French model name mapping - English class names (keys) to French labels (values)
        $modelLabels = [
            // Document-related models
            'Document' => 'document',
            'Invoice' => 'facture',
            'Quote' => 'devis',
            'Quotation' => 'devis',
            'DeliveryNote' => 'bon de livraison',
            'Delivery' => 'bon de livraison',
            'PurchaseOrder' => 'bon de commande',
            'Deposit' => 'facture d\'acompte',
            'CreditNote' => 'avoir',
            'Proforma' => 'facture proforma',
            'BalanceInvoice' => 'facture de solde',
            'RecurringInvoice' => 'facture récurrente',
            'PurchaseInvoice' => 'facture d\'achat',
            'BankRemittance' => 'remise bancaire',
            'PaymentDocument' => 'document de paiement',
            'DocumentItem' => 'article de document',
            'DocumentLink' => 'lien de document',
            // Business entities
            'Customer' => 'client',
            'B2bCustomer' => 'client professionnel',
            'B2cCustomer' => 'client particulier',
            'Supplier' => 'fournisseur',
            'Fournisseur' => 'fournisseur',
            'Product' => 'produit',
            'Expense' => 'dépense',
            'Company' => 'entreprise',
            'User' => 'utilisateur',
            'Role' => 'rôle',
            // Financial
            'Payment' => 'paiement',
            'BankAccount' => 'compte bancaire',
            'CashRegister' => 'caisse',
            'CashRegisterSession' => 'session de caisse',
            'CashTransaction' => 'transaction de caisse',
            // Settings & Configuration
            'ProductCategory' => 'catégorie de produit',
            'TaxRate' => 'taux de TVA',
            'PaymentMode' => 'mode de paiement',
            'PaymentCondition' => 'condition de paiement',
            'LateFeeInterest' => 'intérêts de retard',
            'ProductType' => 'type de produit',
            'DocumentSetting' => 'paramètre de document',
            'DocumentTheme' => 'thème de document',
            'NumberingSerie' => 'série de numérotation',
            'PaymentSetting' => 'paramètre de paiement',
            // Other
            'Media' => 'fichier',
            'Invitation' => 'invitation',
            'DocumentWorkflowHistory' => 'historique de workflow',
            'InvoiceDeduction' => 'déduction de facture',
        ];

        // Capitalize first letter for French output
        $formatModelLabel = function($label) {
            return ucfirst($label);
        };

        // French event labels
        $eventLabels = [
            'created' => 'a créé',
            'updated' => 'a modifié',
            'deleted' => 'a supprimé',
            'restored' => 'a restauré',
        ];

        $modelLabel = isset($modelLabels[$modelName])
            ? $formatModelLabel($modelLabels[$modelName])
            : (ctype_upper($modelName) ? $modelName : ucfirst(strtolower($modelName)));

        $eventLabel = $eventLabels[$eventName] ?? $eventName;

        // Return with HTML markup for blue highlighting of the reference
        return "{$userName} {$eventLabel} {$modelLabel} <span class=\"text-blue-600\">#{$identifier}</span>";
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $companyId = config('app.current_company_id');

        if ($companyId) {
            $activity->company_id = (int) $companyId;
        }

        $properties = $activity->properties->toArray();

        $subjectTitle = $this->number
            ?? $this->name
            ?? $this->title
            ?? $this->company_name
            ?? $this->label
            ?? $this->libelle
            ?? $this->email
            ?? (string) $this->id;

        $properties['subject_title'] = $subjectTitle;
        $activity->properties = $properties;
    }
}