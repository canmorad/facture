<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function getDashboard(int $companyId): array
    {
        try {
            $now = Carbon::now();
            $twelveMonthsAgo = $now->copy()->subMonths(12)->startOfMonth();

            return [
                'stats' => $this->getStats($companyId),
                'revenue_by_month' => $this->getRevenueByMonth($companyId, $twelveMonthsAgo, $now),
                'expenses_by_month' => $this->getExpensesByMonth($companyId, $twelveMonthsAgo, $now),
                'documents_by_type' => $this->getDocumentsByType($companyId),
                'recent_activities' => $this->getRecentActivities($companyId),
                'top_customers' => $this->getTopCustomers($companyId),
                'pending_documents' => $this->getPendingDocuments($companyId),
            ];
        } catch (\Throwable $e) {
            Log::error('DashboardService error: ' . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    protected function getStats(int $companyId): array
    {
        $totalRevenue = Document::where('company_id', $companyId)
            ->whereHasMorph('documentable', [
                'App\\Models\\Invoice',
                'App\\Models\\Deposit',
            ], function ($query) {
                $query->whereIn('status', ['FINALIZED', 'SENT', 'PAID']);
            })
            ->sum('total_ttc');

        $totalExpenses = Expense::where('company_id', $companyId)->sum('total_ttc');

        $activeCustomers = Customer::where('company_id', $companyId)->count();

        $totalDocuments = Document::where('company_id', $companyId)->count();

        return [
            'total_revenue' => round((float) $totalRevenue, 2),
            'total_expenses' => round((float) $totalExpenses, 2),
            'active_customers' => $activeCustomers,
            'total_documents' => $totalDocuments,
        ];
    }

    protected function getRevenueByMonth(int $companyId, Carbon $from, Carbon $to): array
    {
        $revenues = Document::where('company_id', $companyId)
            ->whereHasMorph('documentable', [
                'App\\Models\\Invoice',
                'App\\Models\\Deposit',
            ], function ($query) {
                $query->whereIn('status', ['FINALIZED', 'SENT', 'PAID']);
            })
            ->whereBetween('created_at', [$from, $to->copy()->endOfMonth()])
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_ttc) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        return $this->fillMonthlySeries($revenues, $from, $to);
    }

    protected function getExpensesByMonth(int $companyId, Carbon $from, Carbon $to): array
    {
        $expenses = Expense::where('company_id', $companyId)
            ->whereBetween('issue_date', [$from, $to->copy()->endOfMonth()])
            ->selectRaw('DATE_FORMAT(issue_date, "%Y-%m") as month, SUM(total_ttc) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        return $this->fillMonthlySeries($expenses, $from, $to);
    }

    protected function fillMonthlySeries(array $data, Carbon $from, Carbon $to): array
    {
        $series = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m');
            $series[] = [
                'month' => $key,
                'label' => $cursor->translatedFormat('M Y'),
                'total' => round((float) ($data[$key] ?? 0), 2),
            ];
            $cursor->addMonth();
        }

        return $series;
    }

    protected function getDocumentsByType(int $companyId): array
    {
        $documentCounts = Document::where('company_id', $companyId)
            ->selectRaw("
                SUM(CASE WHEN documentable_type = 'App\\\\Models\\\\Quote' THEN 1 ELSE 0 END) as quotes,
                SUM(CASE WHEN documentable_type = 'App\\\\Models\\\\Invoice' THEN 1 ELSE 0 END) as invoices,
                SUM(CASE WHEN documentable_type = 'App\\\\Models\\\\DeliveryNote' THEN 1 ELSE 0 END) as delivery_notes,
                SUM(CASE WHEN documentable_type = 'App\\\\Models\\\\PurchaseOrder' THEN 1 ELSE 0 END) as purchase_orders,
                SUM(CASE WHEN documentable_type = 'App\\\\Models\\\\Deposit' THEN 1 ELSE 0 END) as deposits,
                SUM(CASE WHEN documentable_type = 'App\\\\Models\\\\CreditNote' THEN 1 ELSE 0 END) as credit_notes
            ")
            ->first();

        if (!$documentCounts) {
            return [];
        }

        return [
            ['type' => 'Devis', 'icon' => 'fa-file-alt', 'color' => 'blue', 'count' => (int) ($documentCounts->quotes ?? 0)],
            ['type' => 'Factures', 'icon' => 'fa-file-invoice', 'color' => 'emerald', 'count' => (int) ($documentCounts->invoices ?? 0)],
            ['type' => 'Bons de livraison', 'icon' => 'fa-truck', 'color' => 'amber', 'count' => (int) ($documentCounts->delivery_notes ?? 0)],
            ['type' => 'Bons de commande', 'icon' => 'fa-shopping-cart', 'color' => 'purple', 'count' => (int) ($documentCounts->purchase_orders ?? 0)],
            ['type' => 'Avoirs', 'icon' => 'fa-undo', 'color' => 'red', 'count' => (int) ($documentCounts->credit_notes ?? 0)],
            ['type' => 'Acomptes', 'icon' => 'fa-hand-holding-usd', 'color' => 'teal', 'count' => (int) ($documentCounts->deposits ?? 0)],
        ];
    }

    protected function getRecentActivities(int $companyId): Collection
    {
        return $this->activityLogService->getRecentActivities(10);
    }

    protected function getTopCustomers(int $companyId): array
    {
        return Document::where('company_id', $companyId)
            ->whereHasMorph('documentable', [
                'App\\Models\\Invoice',
                'App\\Models\\Deposit',
            ], function ($query) {
                $query->whereIn('status', ['FINALIZED', 'SENT', 'PAID']);
            })
            ->whereNotNull('customer_id')
            ->with('customer')
            ->selectRaw('customer_id, SUM(total_ttc) as total_revenue, COUNT(*) as document_count')
            ->groupBy('customer_id')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(function ($doc) {
                $name = $doc->customer->name ?? 'Client inconnu';
                $city = $doc->customer->city ?? '';
                return [
                    'id' => $doc->customer_id,
                    'name' => $name,
                    'city' => $city,
                    'total_revenue' => round((float) $doc->total_revenue, 2),
                    'document_count' => (int) $doc->document_count,
                ];
            })
            ->toArray();
    }

    protected function getPendingDocuments(int $companyId): array
    {
        $draftInvoices = Document::where('company_id', $companyId)
            ->where('documentable_type', 'App\\Models\\Invoice')
            ->whereHasMorph('documentable', ['App\\Models\\Invoice'], function ($query) {
                $query->where('status', 'DRAFT');
            })
            ->count();

        $sentInvoices = Document::where('company_id', $companyId)
            ->where('documentable_type', 'App\\Models\\Invoice')
            ->whereHasMorph('documentable', ['App\\Models\\Invoice'], function ($query) {
                $query->whereIn('status', ['SENT', 'FINALIZED']);
            })
            ->count();

        $overdueInvoices = Document::where('company_id', $companyId)
            ->where('documentable_type', 'App\\Models\\Invoice')
            ->whereHasMorph('documentable', ['App\\Models\\Invoice'], function ($query) {
                $query->where('status', 'OVERDUE');
            })
            ->count();

        $totalPendingAmount = Document::where('company_id', $companyId)
            ->where('documentable_type', 'App\\Models\\Invoice')
            ->whereHasMorph('documentable', ['App\\Models\\Invoice'], function ($query) {
                $query->whereIn('status', ['SENT', 'OVERDUE', 'FINALIZED']);
            })
            ->sum('total_ttc');

        return [
            'draft' => $draftInvoices,
            'sent' => $sentInvoices,
            'overdue' => $overdueInvoices,
            'total_pending_amount' => round((float) $totalPendingAmount, 2),
        ];
    }
}
