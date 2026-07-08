<?php

namespace App\Http\Controllers;

use App\Services\TvaReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TvaReportController extends Controller
{
    protected TvaReportService $tvaReportService;

    public function __construct(TvaReportService $tvaReportService)
    {
        $this->tvaReportService = $tvaReportService;
    }

    public function __invoke(Request $request)
    {
        Gate::authorize('view-tva-report');

        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise sélectionnée.'], 400);
        }

        $period = $request->query('period');

        $report = $this->tvaReportService->generate($companyId, $period);

        return response()->json($report);
    }
}
