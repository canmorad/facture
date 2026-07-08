<?php

namespace App\Http\Controllers;

use App\Mail\SendDocumentMail;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDocumentController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_id' => 'required|integer|exists:documents,id',
            'to_email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
            'sender_name' => 'required|string|max:255',
            'sender_email' => 'required|email|max:255',
        ]);

        try {
            $document = Document::with(['company', 'customer.customerable', 'items'])
                ->findOrFail($validated['document_id']);

            // Vérifier que le document appartient à la compagnie courante
            $companyId = $this->getCompanyId();
            if ((int) $document->company_id !== $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document non autorisé.',
                ], 403);
            }

            try {
                Mail::to($validated['to_email'])
                    ->send(new SendDocumentMail(
                        document: $document,
                        customSubject: $validated['subject'],
                        customMessage: $validated['message'] ?? '',
                        senderName: $validated['sender_name'],
                        senderEmail: $validated['sender_email'],
                    ));
            } catch (\Throwable $e) {
                Log::error('Send document mail failed: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Email envoyé avec succès à ' . $validated['to_email'],
            ]);
        } catch (\Throwable $e) {
            Log::error('SendDocument error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'envoi.',
            ], 500);
        }
    }
}