<?php

namespace App\Http\Controllers\Auth;

use App\Models\NumberingSerie;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserStatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $user->load('companies');

        $hasCompany = $user->companies()->exists();
        $hasNumbering = false;

        if ($hasCompany) {
            $company = $user->companies()->first();
            $hasNumbering = NumberingSerie::where('company_id', $company->id)->exists();
        }

        return response()->json([
            'user' => $user,
            'email_verified' => $user->hasVerifiedEmail(),
            'has_company' => $hasCompany,
            'has_numbering' => $hasNumbering,
        ]);
    }
}