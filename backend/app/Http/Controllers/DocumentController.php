<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    public function destroy(int $id): JsonResponse
    {
        try {
            $companyId = $this->getCompanyId();

            $document = Document::where('company_id', $companyId)
                ->with('documentable')
                ->findOrFail($id);

            // Vérifier si le document est finalisé (ou payé) - on empêche la suppression
            $documentable = $document->documentable;
            if ($documentable && method_exists($documentable, 'getAttribute')) {
                $status = $documentable->getAttribute('status');
                if ($status && !in_array($status, ['DRAFT', 'CANCELLED'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible de supprimer un document finalisé. Veuillez d\'abord l\'annuler.',
                    ], 422);
                }
            }

            // Vérifier si le document a des enfants (documents liés)
            if ($document->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce document ne peut pas être supprimé car il a des documents liés.',
                ], 422);
            }

            // Supprimer la partie polymorphique d'abord
            if ($document->documentable) {
                $document->documentable->delete();
            }

            $document->items()->delete();
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document supprimé avec succès.',
            ]);
        } catch (\Throwable $e) {
            Log::error("Document destroy error ID {$id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du document.',
            ], 500);
        }
    }
}