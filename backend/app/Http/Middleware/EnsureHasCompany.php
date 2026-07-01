<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasCompany
{
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'non_autorisé'], 401);
        }

        if (!$user->companies()->exists()) {
            return response()->json([
                'error' => 'entreprise_requise',
                'message' => 'Vous devez créer une entreprise avant d\'accéder à cette ressource.',
            ], 403);
        }

        return $next($request);
    }
}