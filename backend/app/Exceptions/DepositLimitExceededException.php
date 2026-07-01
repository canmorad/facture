<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class DepositLimitExceededException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'Le montant cumulé des acomptes ne peut pas dépasser le total du devis.',
        ], 422);
    }
}