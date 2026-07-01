<?php

namespace App\Services;

use App\Models\NumberingSerie;

class NumberingService
{
    protected array $docTypeMapping = [
        'invoice' => 'F',
        'quote' => 'D',
        'credit_note' => 'A',
        'deposit_invoice' => 'FA',
        'deposit_credit_note' => 'AA',
        'balance_invoice' => 'FS',
        'delivery_note' => 'BL',
        'purchase_order' => 'BC',
    ];

    public function generateNumber(string $type, int $companyId): string
    {
        $numbering = NumberingSerie::where('company_id', $companyId)->firstOrFail();
        $field = 'current_' . $type;
        $counter = $numbering->{$field} ?? 1;
        $minSize = $numbering->min_size;
        $format = $numbering->format;

        $doc = $this->docTypeMapping[$type] ?? strtoupper($type);
        $year = date('Y');
        $aa = substr($year, -2);
        $month = date('m');
        $m = date('n');
        $day = date('d');
        $j = date('j');
        $cmp = str_pad($counter, $minSize, '0', STR_PAD_LEFT);

        $replacements = [
            '<doc>' => $doc,
            '<aa>' => $aa,
            '<cmp>' => $cmp,
            '<aaaa>' => $year,
            '<mm>' => $month,
            '<m>' => $m,
            '<jj>' => $day,
            '<j>' => $j,
        ];

        $number = str_replace(array_keys($replacements), array_values($replacements), $format);

        $numbering->increment($field);

        return $number;
    }
}