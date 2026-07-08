<?php

namespace App\Http\Controllers;

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