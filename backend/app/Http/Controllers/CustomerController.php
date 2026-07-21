<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\B2bCustomer;
use App\Models\B2cCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use OpenApi\Annotations as OA;

class CustomerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/customers",
     *     summary="Get all customers",
     *     description="Get a list of all customers for the current company",
     *     operationId="getCustomers",
     *     tags={"Customers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Response(
     *         response="200",
     *         description="Customers retrieved successfully",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Customer")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur interne est survenue.")
     *         )
     *     )
     * )
     */
    public function index()
    {
        Gate::authorize('view-customers');
        try {
            $companyId = $this->getCompanyId();
            $customers = Customer::with('customerable')
                ->where('company_id', $companyId)
                ->latest()
                ->get();

            return response()->json($customers);
        } catch (\Throwable $e) {
            Log::error('Customer index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/customers",
     *     summary="Create a new customer",
     *     description="Create a new B2B or B2C customer for the current company",
     *     operationId="createCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"type"},
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"b2b", "b2c"},
     *                     description="Customer type",
     *                     example="b2b"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     maxLength=255,
     *                     nullable=true,
     *                     example="customer@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     maxLength=50,
     *                     nullable=true,
     *                     example="+212512345678"
     *                 ),
     *                 @OA\Property(
     *                     property="address_street",
     *                     type="string",
     *                     maxLength=500,
     *                     nullable=true,
     *                     example="123 Customer Street"
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
     *                     maxLength=20,
     *                     nullable=true,
     *                     example="20000"
     *                 ),
     *                 @OA\Property(
     *                     property="country",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     example="Maroc"
     *                 ),
     *                 @OA\Property(
     *                     property="notes",
     *                     type="string",
     *                     nullable=true,
     *                     example="VIP customer"
     *                 ),
     *                 @OA\Property(
     *                     property="is_active",
     *                     type="boolean",
     *                     example=true
     *                 ),
     *                 @OA\Property(
     *                     property="legal_name",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Required for B2B customers",
     *                     nullable=true,
     *                     example="Acme Corporation"
     *                 ),
     *                 @OA\Property(
     *                     property="ice",
     *                     type="string",
     *                     maxLength=50,
     *                     description="Required for B2B customers",
     *                     nullable=true,
     *                     example="00123456789"
     *                 ),
     *                 @OA\Property(
     *                     property="rc",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     description="B2B RC number"
     *                 ),
     *                 @OA\Property(
     *                     property="if",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     description="B2B IF number"
     *                 ),
     *                 @OA\Property(
     *                     property="patente",
     *                     type="string",
     *                     maxLength=255,
     *                     nullable=true,
     *                     description="B2B Patente number"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Required for B2C customers",
     *                     nullable=true,
     *                     example="John Doe"
     *                 ),
     *                 @OA\Property(
     *                     property="cin",
     *                     type="string",
     *                     maxLength=50,
     *                     description="B2C CIN number",
     *                     nullable=true
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="201",
     *         description="Customer created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Customer")
     *     ),
     *
     *     @OA\Response(
     *         response="422",
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la création.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        Gate::authorize('create-customer');
        try {
            $companyId = $this->getCompanyId();

            $request->validate([
                'type' => ['required', Rule::in(['b2b', 'b2c'])],
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'address_street' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ]);

            return DB::transaction(function () use ($request, $companyId) {
                if ($request->type === 'b2b') {
                    $b2bData = $request->validate([
                        'legal_name' => 'required|string|max:255',
                        'ice' => 'required|string|max:50|unique:b2b_customers,ice',
                        'rc' => 'nullable|string|max:255',
                        'if' => 'nullable|string|max:255',
                        'patente' => 'nullable|string|max:255',
                    ]);

                    $customerable = B2bCustomer::create($b2bData);
                } else {
                    $b2cData = $request->validate([
                        'name' => 'required|string|max:255',
                        'cin' => 'nullable|string|max:50|unique:b2c_customers,cin',
                    ]);

                    $customerable = B2cCustomer::create($b2cData);
                }

                $customer = Customer::create([
                    'company_id' => $companyId,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address_street' => $request->address_street,
                    'city' => $request->city,
                    'postal_code' => $request->postal_code,
                    'country' => $request->country ?? 'Maroc',
                    'notes' => $request->notes,
                    'is_active' => $request->is_active ?? true,
                    'type' => $request->type,
                    'customerable_type' => get_class($customerable),
                    'customerable_id' => $customerable->id,
                ]);

                return response()->json($customer->load('customerable'), 201);
            });
        } catch (\Throwable $e) {
            Log::error('Customer store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la création.'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/customers/{id}",
     *     summary="Get a customer by ID",
     *     description="Get a specific customer by ID for the current company",
     *     operationId="getCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Customer retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Customer")
     *     ),
     *
     *     @OA\Response(
     *         response="404",
     *         description="Customer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Client introuvable.")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        Gate::authorize('view-customers');
        try {
            $companyId = $this->getCompanyId();
            $customer = Customer::with('customerable')
                ->where('company_id', $companyId)
                ->findOrFail($id);

            return response()->json($customer);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Client introuvable.'], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/customers/{id}",
     *     summary="Update a customer",
     *     description="Update a specific customer by ID",
     *     operationId="updateCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="email", type="string", format="email", maxLength=255, nullable=true),
     *                 @OA\Property(property="phone", type="string", maxLength=50, nullable=true),
     *                 @OA\Property(property="address_street", type="string", maxLength=500, nullable=true),
     *                 @OA\Property(property="city", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="postal_code", type="string", maxLength=20, nullable=true),
     *                 @OA\Property(property="country", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="notes", type="string", nullable=true),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="legal_name", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="ice", type="string", maxLength=50, nullable=true),
     *                 @OA\Property(property="rc", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="if", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="patente", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="name", type="string", maxLength=255, nullable=true),
     *                 @OA\Property(property="cin", type="string", maxLength=50, nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Customer updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Customer")
     *     ),
     *
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la mise à jour.")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('edit-customer');
        try {
            $companyId = $this->getCompanyId();
            $customer = Customer::with('customerable')
                ->where('company_id', $companyId)
                ->findOrFail($id);

            $request->validate([
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'address_street' => 'nullable|string|max:500',
                'city' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ]);

            return DB::transaction(function () use ($request, $customer) {
                $customer->update([
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'address_street' => $request->address_street,
                    'city' => $request->city,
                    'postal_code' => $request->postal_code,
                    'country' => $request->country ?? 'Maroc',
                    'notes' => $request->notes,
                    'is_active' => $request->is_active ?? true,
                ]);

                $customerable = $customer->customerable;
                if ($customer->type === 'b2b') {
                    $data = $request->validate([
                        'legal_name' => 'required|string|max:255',
                        'ice' => [
                            'required', 'string', 'max:50',
                            Rule::unique('b2b_customers', 'ice')->ignore($customer->customerable_id),
                        ],
                        'rc' => 'nullable|string|max:255',
                        'if' => 'nullable|string|max:255',
                        'patente' => 'nullable|string|max:255',
                    ]);
                    $customerable->update($data);
                } else {
                    $data = $request->validate([
                        'name' => 'required|string|max:255',
                        'cin' => [
                            'nullable', 'string', 'max:50',
                            Rule::unique('b2c_customers', 'cin')->ignore($customer->customerable_id),
                        ],
                    ]);
                    $customerable->update($data);
                }

                return response()->json($customer->load('customerable'));
            });
        } catch (\Throwable $e) {
            Log::error("Customer update error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}",
     *     summary="Delete a customer",
     *     description="Delete a specific customer by ID",
     *     operationId="deleteCustomer",
     *     tags={"Customers"},
     *     security={{"sanctum":{}}, {"check.company":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response="204",
     *         description="Customer deleted successfully"
     *     ),
     *
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Une erreur est survenue lors de la suppression.")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        Gate::authorize('delete-customer');
        try {
            $companyId = $this->getCompanyId();
            $customer = Customer::where('company_id', $companyId)->findOrFail($id);

            DB::transaction(function () use ($customer) {
                $customer->customerable->delete();
                $customer->delete();
            });

            return response()->json(null, 204);
        } catch (\Throwable $e) {
            Log::error("Customer destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la suppression.'], 500);
        }
    }
}
