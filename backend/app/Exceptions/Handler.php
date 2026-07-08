<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (InvalidStatusTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], $e->getCode());
        });

        $this->renderable(function (ImmutableDocumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], $e->getCode());
        });

        $this->renderable(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Les données fournies ne sont pas valides.',
                'errors' => $e->errors(),
            ], 422);
        });

        $this->renderable(function (NotFoundHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La ressource demandée est introuvable.',
                'errors' => [],
            ], 404);
        });

        $this->renderable(function (AccessDeniedHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
                'errors' => [],
            ], 403);
        });

        $this->renderable(function (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 422);
        });

        $this->renderable(function (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
            ], 400);
        });

        $this->renderable(function (Throwable $e) {
            if (config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [
                        [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ],
                    ],
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue. Veuillez réessayer plus tard.',
                'errors' => [],
            ], 500);
        });
    }
}