<?php

namespace App\Http\Controllers;

use App\Models\DocumentTheme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentThemeController extends Controller
{
    protected function getCompanyId(Request $request)
    {
        return $request->input('company_id') ?? auth()->user()->currentCompanyId;
    }

    public function show(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

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
    }

    public function update(Request $request)
    {
        $companyId = $this->getCompanyId($request);
        if (!$companyId) {
            return response()->json(['error' => 'Aucune entreprise trouvée'], 400);
        }

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
    }
}