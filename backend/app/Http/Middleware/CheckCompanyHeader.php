<?php

namespace App\Http\Middleware;

use App\Models\UserCompany;
use App\Models\Role;
use Closure;
use Illuminate\Http\Request;

class CheckCompanyHeader
{
    protected array $except = [
        'api/companies',
    ];

    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();

        foreach ($this->except as $excluded) {
            if ($path === $excluded) {
                return $next($request);
            }
        }

        $companyId = $request->header('X-Company-Id');
        $user = $request->user();

        if (!$companyId && $user) {
            $companyId = $this->resolveFallbackCompany($user);

            if (!$companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez sélectionner une entreprise.',
                ], 400);
            }
        }

        if ($user && !$user->companies()->where('companies.id', (int) $companyId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à cette entreprise.',
            ], 403);
        }

        config(['app.current_company_id' => (int) $companyId]);

        return $next($request);
    }

    private function resolveFallbackCompany($user): ?int
    {
        $userCompany = UserCompany::where('user_id', $user->id)->first();

        if (!$userCompany) {
            return null;
        }

        return (int) $userCompany->company_id;
    }
}
