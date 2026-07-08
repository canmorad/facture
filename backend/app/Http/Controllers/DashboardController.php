<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('view-documents');
        try {
            $companyId = config('app.current_company_id');

            if (!$companyId) {
                return response()->json(['message' => 'Veuillez selectionner une entreprise.'], 400);
            }

            $data = $this->dashboardService->getDashboard($companyId);
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('DashboardController index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Une erreur interne est survenue.'], 500);
        }
    }
}
