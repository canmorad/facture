<?php

namespace App\Http\Controllers;

use App\Models\BankRemittance;
use App\Models\Company;
use App\Services\BankRemittanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BankRemittancePreviewController extends Controller
{
    public function __construct(
        protected BankRemittanceService $remittanceService
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();

            $remittance = BankRemittance::with([
                'bankAccount',
                'paymentDocuments.customer.customerable',
                'paymentDocuments.document',
                'company',
            ])->where('id', $id)
                ->where('company_id', $companyId)
                ->firstOrFail();

            // Get company data
            $company = $remittance->company;
            if ($company) {
                $company->logo = $company->logo ? Storage::url($company->logo) : null;
            }

            return response()->json([
                'remittance' => $remittance,
                'company' => $company,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Remise introuvable.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error("BankRemittancePreview show error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue.',
            ], 500);
        }
    }
}
