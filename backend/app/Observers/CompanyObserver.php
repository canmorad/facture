<?php

namespace App\Observers;

use App\Models\Company;
use App\Models\ProductCategory;
use App\Models\PaymentCondition;
use App\Models\PaymentMode;
use App\Models\LateFeeInterest;
use App\Models\DocumentTheme;

class CompanyObserver
{
    public function created(Company $company): void
    {
        // 1. Product Categories
        $categories = [
            'Nom',
            'Acompte',
            'Heures',
            'Jours',
            'Produit',
            'Service',
        ];

        foreach ($categories as $index => $name) {
            ProductCategory::create([
                'company_id' => $company->id,
                'name' => $name,
                'is_default' => $index === 0,
                'is_active' => true,
                'description' => null,
            ]);
        }

        // 2. Payment Conditions
        $conditions = [
            'À réception',
            'Fin de mois',
            '10 jours',
            '30 jours',
            '30 jours fin de mois',
            '45 jours fin de mois',
        ];

        foreach ($conditions as $index => $label) {
            PaymentCondition::create([
                'company_id' => $company->id,
                'label' => $label,
                'is_default' => $index === 0,
                'is_active' => true,
            ]);
        }

        // 3. Payment Modes
        $modes = [
            'Non spécifié',
            'Espèces',
            'Chèque',
            'Virement bancaire',
            'Carte bancaire',
            'PayPal',
        ];

        foreach ($modes as $index => $label) {
            PaymentMode::create([
                'company_id' => $company->id,
                'label' => $label,
                'is_default' => $label === 'Virement bancaire',
                'is_active' => true,
            ]);
        }

        // 4. Late Fee Interests
        $interests = [
            'Pas d’intérêts de retard',
            '1% par mois',
            '1.5% par mois',
            '2% par mois',
            'Taux d’intérêt légal en vigueur',
            'À préciser',
        ];

        foreach ($interests as $index => $label) {
            LateFeeInterest::create([
                'company_id' => $company->id,
                'label' => $label,
                'is_default' => $index === 0,
                'is_active' => true,
            ]);
        }

        // 5. Document Theme (default theme)
        DocumentTheme::create([
            'company_id' => $company->id,
            'font_family' => 'Nunito',
            'primary_color' => '#062121',
            'background_pattern' => 'none',
            'table_border_style' => 'sharp',
            'table_line_style' => 'standard',
        ]);

        // ❌ Numbering Series is **no longer** created here.
        // It must be configured explicitly by the user during onboarding.
    }
}