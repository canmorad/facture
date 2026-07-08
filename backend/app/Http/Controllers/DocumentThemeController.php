<?php

namespace App\Http\Controllers;

use App\Models\DocumentTheme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class DocumentThemeController extends Controller
{
    public function show()
    {
        try {
            $companyId = $this->getCompanyId();
            $theme = DocumentTheme::where('company_id', $companyId)->first();
            if (!$theme) {
                $theme = DocumentTheme::create([
                    'company_id' => $companyId,
                    'font_family' => 'Nunito',
                    'primary_color' => '#062121',
                    'background_pattern' => 'none',
                    'table_border_style' => 'sharp',
                    'table_line_style' => 'standard',
                ]);
            }

            return response()->json($theme);
        } catch (\Throwable $e) {
            Log::error('DocumentTheme show error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur interne est survenue.'], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $companyId = $this->getCompanyId();
            $theme = DocumentTheme::where('company_id', $companyId)->first();
            if (!$theme) {
                $theme = DocumentTheme::create([
                    'company_id' => $companyId,
                    'font_family' => 'Nunito',
                    'primary_color' => '#062121',
                    'background_pattern' => 'none',
                    'table_border_style' => 'sharp',
                    'table_line_style' => 'standard',
                ]);
            }

            $validated = $request->validate([
                'font_family' => 'sometimes|string|max:255',
                'primary_color' => 'sometimes|string|max:7',
                'background_pattern' => ['sometimes', Rule::in(['none', 'dots', 'lines', 'grid'])],
                'table_border_style' => ['sometimes', Rule::in(['sharp', 'rounded', 'none'])],
                'table_line_style' => ['sometimes', Rule::in(['standard', 'bold', 'dashed', 'none'])],
            ]);

            $theme->update($validated);

            return response()->json($theme);
        } catch (\Throwable $e) {
            Log::error('DocumentTheme update error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['success' => false, 'message' => 'Une erreur est survenue lors de la mise à jour.'], 500);
        }
    }
}