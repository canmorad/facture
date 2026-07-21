<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Facturex API",
    version: "1.0.0",
    description: "API documentation for Facturex application"
)]
#[OA\Server(
    url: "http://localhost:8001",
    description: "API Server"
)]
#[OA\PathItem(
    path: "/api/health",
    description: "Health check endpoint"
)]
abstract class Controller
{

    protected function getCompanyId(): int
    {
        $companyId = config('app.current_company_id');
        if (!$companyId) {
            throw new \RuntimeException('Aucune entreprise sélectionnée.');
        }
        return (int) $companyId;
    }
}