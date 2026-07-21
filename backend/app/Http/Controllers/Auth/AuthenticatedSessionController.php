<?php
// app/Http/Controllers/Auth/AuthenticatedSessionController.php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class AuthenticatedSessionController extends Controller
{

    #[OA\Post(
        path: '/api/login',
        summary: 'Authenticate user',
        description: 'Login endpoint that validates credentials and returns user data with authentication token',
        operationId: 'login',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['email', 'password'],
                    properties: [
                        new OA\Property(
                            property: 'email',
                            type: 'string',
                            format: 'email',
                            description: 'User email address',
                            example: 'user@example.com'
                        ),
                        new OA\Property(
                            property: 'password',
                            type: 'string',
                            format: 'password',
                            description: 'User password',
                            example: 'password123'
                        ),
                        new OA\Property(
                            property: 'remember',
                            type: 'boolean',
                            description: 'Remember user session',
                            example: false
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'user',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time', nullable: true),
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'email_verified', type: 'boolean', example: false),
                        new OA\Property(property: 'has_company', type: 'boolean', example: false)
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'errors', type: 'object', example: ['email' => ['The email field is required.']]),
                    ]
                )
            )
        ]
    )]
    public function store(LoginRequest $request): JsonResponse
{
    $request->authenticate();
    $request->session()->regenerate();

    $user = Auth::user();

    // Build complete auth response (same format as UserStatusController)
    // to ensure consistent data after login and after page refresh
    $userCompanies = \App\Models\UserCompany::with(['company', 'role'])
        ->where('user_id', $user->id)
        ->get()
        ->map(function ($userCompany) {
            $company = $userCompany->company;
            return [
                'id' => $company->id,
                'name' => $company->company_name,
                'logo' => $company->logo ? \Illuminate\Support\Facades\Storage::url($company->logo) : null,
                'is_owner' => $userCompany->role && $userCompany->role->name === 'owner',
                'role' => $userCompany->role ? $userCompany->role->name : 'Membre',
            ];
        });

    $hasCompany = $userCompanies->isNotEmpty();
    $effectiveCompanyId = $hasCompany ? $userCompanies->first()['id'] : null;

    $isOwner = false;
    $permissions = [];

    if ($effectiveCompanyId) {
        $userCompany = \App\Models\UserCompany::where('user_id', $user->id)
            ->where('company_id', $effectiveCompanyId)
            ->first();

        if ($userCompany && $userCompany->role_id) {
            $role = \App\Models\Role::find($userCompany->role_id);
            if ($role) {
                $isOwner = $role->name === 'owner';
                if ($isOwner) {
                    $allPermissions = config('permissions.roles');
                    $permissions = array_keys(array_flip(array_merge(...array_values($allPermissions))));
                } else {
                    $permissions = config("permissions.roles.{$role->name}", []);
                }
            }
        }
    }

    $hasNumbering = false;
    if ($hasCompany && $effectiveCompanyId) {
        $hasNumbering = \App\Models\NumberingSerie::where('company_id', $effectiveCompanyId)->exists();
    }

    return response()->json([
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_owner' => $isOwner,
            'companies' => $userCompanies,
        ],
        'permissions' => $permissions,
        'email_verified' => $user->hasVerifiedEmail(),
        'has_company' => $hasCompany,
        'has_numbering' => $hasNumbering,
    ]);
}


    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user",
     *     description="Logout endpoint that invalidates the user session",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Logged out"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="401",
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthenticated."
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}