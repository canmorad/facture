<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\UserCompany;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Traits\MediaUpload;
use Illuminate\Support\Facades\Gate;
use OpenApi\Annotations as OA;

class CompanyController extends Controller
{
    use MediaUpload;

    /**
     * @OA\Post(
     *     path="/api/companies",
     *     summary="Create a new company",
     *     description="Creates a new company and assigns the authenticated user as owner",
     *     operationId="createCompany",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"company_name", "email", "phone", "address"},
     *                 @OA\Property(
     *                     property="company_name",
     *                     type="string",
     *                     maxLength=255,
     *                     example="Acme Corporation"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     maxLength=255,
     *                     example="contact@acme.com"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     maxLength=255,
     *                     example="+212512345678"
     *                 ),
     *                 @OA\Property(
     *                     property="address",
     *                     type="string",
     *                     example="123 Business Street, Casablanca"
     *                 ),
     *                 @OA\Property(
     *                     property="logo",
     *                     type="string",
     *                     format="binary",
     *                     description="Company logo image (JPEG, PNG, JPG, GIF, max 2MB)",
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="signature",
     *                     type="string",
     *                     format="binary",
     *                     description="Company signature image (JPEG, PNG, JPG, max 2MB)",
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="ice",
     *                     type="string",
     *                     maxLength=15,
     *                     description="ICE number",
     *                     nullable=true,
     *                     example="00123456789"
     *                 ),
     *                 @OA\Property(
     *                     property="if",
     *                     type="string",
     *                     maxLength=8,
     *                     description="IF number",
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="rc",
     *                     type="string",
     *                     maxLength=255,
     *                     description="RC number",
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="patente",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="cnss",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true
     *                 ),
     *                 @OA\Property(
     *                     property="website",
     *                     type="string",
     *                     format="uri",
     *                     maxLength=255,
     *                     nullable=true,
     *                     example="https://acme.com"
     *                 ),
     *                 @OA\Property(
     *                     property="city",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     example="Casablanca"
     *                 ),
     *                 @OA\Property(
     *                     property="postal_code",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     example="20000"
     *                 ),
     *                 @OA\Property(
     *                     property="country",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     example="Maroc"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Company created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Company")
     *     ),
     *
     *     @OA\Response(
     *         response="422",
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="The given data was invalid."
     *             ),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'ice' => 'nullable|string|max:15',
            'if' => 'nullable|string|max:8',
            'rc' => 'nullable|string|max:255',
            'patente' => 'nullable|string|max:255',
            'cnss' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();

        $logo_path = null;
        $signature_path = null;

        if ($request->hasFile('logo')) {
            $logo_path = $this->upload($request->file('logo'), 'logos');
        }

        if ($request->hasFile('signature')) {
            $signature_path = $this->upload($request->file('signature'), 'signatures');
        }

        $company = Company::create([
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'if' => $request->if,
            'ice' => $request->ice,
            'rc' => $request->rc,
            'patente' => $request->patente,
            'cnss' => $request->cnss,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'country' => $request->country ?? 'Maroc',
            'website' => $request->website,
            'logo' => $logo_path,
            'signature' => $signature_path,
        ]);

        $ownerRole = Role::firstOrCreate(
            ['name' => 'owner'],
            ['description' => 'First user who registers. Master access to everything.']
        );

        UserCompany::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $ownerRole->id,
        ]);

        return response()->json($company, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/company-settings",
     *     summary="Update company settings",
     *     description="Update the current company's settings and information",
     *     operationId="updateCompanySettings",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"company_name", "email", "phone", "address"},
     *                 @OA\Property(property="company_name", type="string", maxLength=255),
     *                 @OA\Property(property="email", type="string", format="email", maxLength=255),
     *                 @OA\Property(property="phone", type="string", maxLength=255),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="logo", type="string", format="binary", nullable=true),
     *                 @OA\Property(property="signature", type="string", format="binary", nullable=true),
     *                 @OA\Property(property="ice", type="string", maxLength=15, nullable=true),
     *                 @OA\Property(property="if", type="string", maxLength=8, nullable=true),
     *                 @OA\Property(property="rc", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="patente", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="cnss", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="website", type="string", format="uri", maxLength=255, nullable=true),
     *                 @OA\Property(property="city", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="postal_code", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="country", type="string", maxLength=255, nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Settings saved successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Vous n'êtes pas autorisé à modifier cette entreprise.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Company not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Entreprise introuvable.")
     *         )
     *     )
     * )
     */
    public function update(Request $request)
    {
        Gate::authorize('view-settings');
        $request->validate([
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'ice' => 'nullable|string|max:15',
            'if' => 'nullable|string|max:8',
            'rc' => 'nullable|string|max:255',
            'patente' => 'nullable|string|max:255',
            'cnss' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'required|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $companyId = config('app.current_company_id');
        $company = Company::find($companyId);

        if (!$company) {
            return response()->json(['error' => 'Entreprise introuvable.'], 404);
        }

        $userCompany = UserCompany::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->first();

        if (!$userCompany) {
            return response()->json(['error' => 'Vous n\'êtes pas autorisé à modifier cette entreprise.'], 403);
        }

        $logo_path = $company->logo ?? null;
        $signature_path = $company->signature ?? null;

        if ($request->hasFile('logo')) {
            if ($logo_path) {
                $this->delete($logo_path);
            }
            $logo_path = $this->upload($request->file('logo'), 'logos');
        }

        if ($request->hasFile('signature')) {
            if ($signature_path) {
                $this->delete($signature_path);
            }
            $signature_path = $this->upload($request->file('signature'), 'signatures');
        }

        $company->update([
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'if' => $request->if,
            'ice' => $request->ice,
            'rc' => $request->rc,
            'patente' => $request->patente,
            'cnss' => $request->cnss,
            'city' => $request->city,
            'postal_code' => $request->postal_code,
            'country' => $request->country ?? 'Maroc',
            'website' => $request->website,
            'logo' => $logo_path,
            'signature' => $signature_path,
        ]);

        return response()->json(['message' => 'Settings saved successfully'], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/company-settings",
     *     summary="Get company settings",
     *     description="Get the current company's settings and information",
     *     operationId="getCompanySettings",
     *     tags={"Companies"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Company settings retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Company")
     *     )
     * )
     */
    public function show(Request $request)
    {
        Gate::authorize('view-settings');
        $companyId = config('app.current_company_id');

        if (!$companyId) {
            return response()->json(null, 200);
        }

        $user = auth()->user();
        $userCompany = UserCompany::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        if (!$userCompany) {
            return response()->json(null, 200);
        }

        $settings = Company::find($companyId);

        if (!$settings) {
            return response()->json(null, 200);
        }

        $settings->logo = $settings->logo ? Storage::url($settings->logo) : null;
        $settings->signature = $settings->signature ? Storage::url($settings->signature) : null;

        return response()->json($settings);
    }

    /**
     * @OA\Get(
     *     path="/api/user/organizations",
     *     summary="Get user organizations",
     *     description="Get all organizations the authenticated user belongs to",
     *     operationId="getUserOrganizations",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Organizations retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Acme Corporation"),
     *                 @OA\Property(property="logo", type="string", nullable=true, example="http://localhost:8001/storage/logos/logo.jpg"),
     *                 @OA\Property(property="is_owner", type="boolean", example=true),
     *                 @OA\Property(property="role", type="string", example="owner")
     *             )
     *         )
     *     )
     * )
     */
    public function userOrganizations(Request $request)
    {
        $user = $request->user();

        $userCompanies = UserCompany::with(['company', 'role'])
            ->where('user_id', $user->id)
            ->get();

        return $userCompanies->map(function ($userCompany) {
            $company = $userCompany->company;
            return [
                'id' => $company->id,
                'name' => $company->company_name,
                'logo' => $company->logo ? Storage::url($company->logo) : null,
                'is_owner' => $userCompany->role && $userCompany->role->name === 'owner',
                'role' => $userCompany->role ? $userCompany->role->name : 'Membre',
            ];
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{id}",
     *     summary="Leave organization",
     *     description="Leave an organization the user is a member of",
     *     operationId="leaveOrganization",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="204",
     *         description="Successfully left the organization"
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Not a member of this organization",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Vous n'appartenez pas à cette organisation.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="422",
     *         description="Cannot leave as owner",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Le propriétaire ne peut pas quitter son organisation.")
     *         )
     *     )
     * )
     */
    public function leaveOrganization(Request $request, $id)
    {
        $user = $request->user();

        $membership = UserCompany::where('user_id', $user->id)
            ->where('company_id', $id)
            ->first();

        if (!$membership) {
            return response()->json([
                'error' => 'Vous n\'appartenez pas à cette organisation.'
            ], 404);
        }

        $role = Role::find($membership->role_id);
        if ($role && $role->name === 'owner') {
            return response()->json([
                'error' => 'Le propriétaire ne peut pas quitter son organisation.'
            ], 422);
        }

        $membership->delete();

        return response()->json(null, 204);
    }
}
