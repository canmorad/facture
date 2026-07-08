<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentItem;
use Carbon\Carbon;

class TvaReportService
{
    public function generate(int $companyId, ?string $period = null): array
    {
        $query = Document::where('company_id', $companyId)
            ->whereHasMorph('documentable', [
                'App\\Models\\Invoice',
                'App\\Models\\Deposit',
                'App\\Models\\CreditNote',
            ], function ($query) {
                $query->where('status', 'FINALIZED');
            });

        if ($period === 'month') {
            $query->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year);
        } elseif ($period === 'quarter') {
            $currentQuarter = ceil(Carbon::now()->month / 3);
            $query->whereRaw('CEIL(MONTH(created_at) / 3) = ?', [$currentQuarter])
                ->whereYear('created_at', Carbon::now()->year);
        }

        $documents = $query->get();
        $documentIds = $documents->pluck('id')->toArray();

        $items = DocumentItem::whereIn('document_id', $documentIds)->get();

        $rates = [];

        foreach ($items as $item) {
            $rate = (float) ($item->tax_rate ?? 0);

            $rateKey = (string) $rate;

            if (!isset($rates[$rateKey])) {
                $rates[$rateKey] = [
                    'rate' => $rate,
                    'total_ht' => 0,
                    'total_tva' => 0,
                    'total_ttc' => 0,
                    'count' => 0,
                ];
            }

            $itemHt = (float) ($item->total_ht ?? 0);
            $itemTtc = (float) ($item->total_ttc ?? 0);
            $itemTva = $rate > 0 ? $itemHt * ($rate / 100) : 0;

            $rates[$rateKey]['total_ht'] += $itemHt;
            $rates[$rateKey]['total_tva'] += $itemTva;
            $rates[$rateKey]['total_ttc'] += $itemTtc;
            $rates[$rateKey]['count']++;
        }

        $totalHt = 0;
        $totalTva = 0;
        $totalTtc = 0;

        foreach ($rates as $data) {
            $totalHt += $data['total_ht'];
            $totalTva += $data['total_tva'];
            $totalTtc += $data['total_ttc'];
        }

        krsort($rates);

        return [
            'rates' => array_values($rates),
            'total_ht' => round($totalHt, 2),
            'total_tva' => round($totalTva, 2),
            'total_ttc' => round($totalTtc, 2),
            'period' => $period ?? 'all',
            'document_count' => $documents->count(),
        ];
    }
}