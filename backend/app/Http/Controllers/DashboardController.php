<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/dashboard",
     *     summary="Get dashboard data",
     *     description="Get dashboard statistics and summary data",
     *     operationId="getDashboard",
     *     tags={"Dashboard"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="stats", type="object"),
     *             @OA\Property(property="recent_invoices", type="array", @OA\Items()),
     *             @OA\Property(property="recent_quotes", type="array", @OA\Items()),
     *             @OA\Property(property="overdue_invoices", type="array", @OA\Items()),
     *             @OA\Property(property="revenue_chart", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
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
