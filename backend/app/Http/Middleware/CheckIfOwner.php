<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckIfOwner
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification requise.',
            ], 401);
        }

        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune entreprise sélectionnée.',
            ], 400);
        }

        $pivot = $user->companies()
            ->where('company_id', $companyId)
            ->first()?->pivot;

        if (!$pivot || !$pivot->role_id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas les droits nécessaires.',
            ], 403);
        }

        $role = \App\Models\Role::find($pivot->role_id);

        if (!$role || $role->name !== 'owner') {
            return response()->json([
                'success' => false,
                'message' => 'Seul le propriétaire peut effectuer cette action.',
            ], 403);
        }

        return $next($request);
    }
}