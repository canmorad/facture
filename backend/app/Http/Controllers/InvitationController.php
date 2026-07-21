<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvitationController extends Controller
{
    public function __construct(protected InvitationService $invitationService) {}

    public function verify(string $token): JsonResponse
    {
        try {
            $result = $this->invitationService->verifyToken($token);

            if (!$result['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Invitation verify error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification.',
            ], 500);
        }
    }

    public function accept(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        try {
            $user = $this->invitationService->accept(
                $request->input('token'),
                $request->input('name'),
                $request->input('password'),
            );

            Auth::login($user);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès.',
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Invitation accept error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du compte.',
            ], 500);
        }
    }

    public function acceptForExistingUser(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié.',
                ], 401);
            }

            $result = $this->invitationService->acceptForExistingUser(
                $user,
                $request->input('token')
            );

            return response()->json([
                'success' => true,
                'message' => 'Vous avez été ajouté à l\'entreprise avec succès.',
                'company' => $result['company'],
                'user' => $result['user'],
                'already_linked' => $result['already_linked'] ?? false,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Invitation accept for existing user error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'acceptation de l\'invitation.',
            ], 500);
        }
    }
}