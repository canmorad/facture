@php
    $primaryColor = $theme->primary_color ?? '#062121';
    $fontFamily = $theme->font_family ?? 'DejaVu Sans, sans-serif';
    $tableLineStyle = $theme->table_line_style ?? 'standard';
    $bgPattern = $theme->background_pattern ?? 'none';
    $tableBorderStyle = $theme->table_border_style ?? 'sharp';
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 16mm; }
        body { font-family: {{ $fontFamily }}; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
        .header-section { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; border-bottom: 2px solid {{ $primaryColor }}40; padding-bottom: 16px; }
        .header-left h1 { color: {{ $primaryColor }}; font-size: 20px; margin: 0 0 4px; }
        .header-left .number { font-size: 13px; color: #64748b; }
        .header-right { text-align: right; }
        .header-right .doc-label { font-size: 24px; font-weight: 900; text-transform: uppercase; color: {{ $primaryColor }}; }
        .header-right .doc-number { font-size: 16px; font-weight: 700; color: #334155; }
        .header-right .doc-date { font-size: 12px; color: #64748b; margin-top: 4px; }
        .info-grid { display: flex; width: 100%; margin-bottom: 24px; gap: 40px; }
        .info-col { flex: 1; }
        .info-col h3 { font-size: 10px; color: {{ $primaryColor }}; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px; font-weight: 700; }
        .info-col p { margin: 2px 0; line-height: 1.6; font-size: 10px; }
        .info-col strong { color: {{ $primaryColor }}; }
        table { width: 100%; border-collapse: collapse; margin: 24px 0;
            @if($tableBorderStyle === 'rounded') border-radius: 4px; overflow: hidden; @endif
        }
        th { background: {{ $primaryColor }}; padding: 6px 8px; text-align: left; font-size: 9px; color: #ffffff; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 6px 8px; font-size: 10px;
            @if($tableLineStyle === 'none') border-bottom: none;
            @elseif($tableLineStyle === 'bold') border-bottom: 2px solid #e5e7eb;
            @elseif($tableLineStyle === 'dashed') border-bottom: 1px dashed #d1d5db;
            @else border-bottom: 1px solid #e5e7eb;
            @endif
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals table { margin: 0; }
        .totals td { padding: 4px 10px; font-size: 10px; border: none; }
        .totals .total-row { font-weight: bold; color: {{ $primaryColor }}; font-size: 12px; border-top: 2px solid {{ $primaryColor }}; }
        .notes { margin-top: 30px; padding: 10px; font-size: 9px; color: #64748b; line-height: 1.5; }
        .footer { margin-top: 40px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="header-left">
            @if($document->company && $document->company->logo)
                <img src="{{ public_path('storage/' . $document->company->logo) }}" style="max-height: 50px; width: auto; margin-bottom: 8px;" alt="Logo" />
            @endif
            <h1>{{ $document->company->company_name ?? config('app.name', 'Facturex') }}</h1>
            <div class="number" style="font-size: 10px;">
                @if($document->company)
                    {{ $document->company->address }}<br>
                    {{ $document->company->city }}, {{ $document->company->country }} {{ $document->company->postal_code }}<br>
                    {{ $document->company->phone }} | {{ $document->company->email }}<br>
                    @if($document->company->ice) ICE: {{ $document->company->ice }} | @endif
                    @if($document->company->if) IF: {{ $document->company->if }} | @endif
                    @if($document->company->rc) RC: {{ $document->company->rc }} @endif
                @endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-label">{{ $docLabel ?? 'DOCUMENT' }}</div>
            <div class="doc-number">#{{ $document->number ?? '—' }}</div>
            <div class="doc-date">{{ $document->created_at?->format('d/m/Y') ?? '' }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-col">
            <h3>&Eacute;metteur</h3>
            @if($document->company)
                <p><strong>{{ $document->company->company_name }}</strong></p>
                <p>{{ $document->company->address }}</p>
                <p>{{ $document->company->postal_code }} {{ $document->company->city }}</p>
                <p>{{ $document->company->email }}</p>
            @endif
        </div>
        <div class="info-col">
            <h3>Destinataire</h3>
            @if($document->customer)
                <p><strong>{{ $document->customer->name }}</strong></p>
                <p>{{ $document->customer->address_street }}</p>
                <p>{{ $document->customer->postal_code }} {{ $document->customer->city }}</p>
                <p>{{ $document->customer->country }}</p>
                <p>{{ $document->customer->email }}</p>
            @endif
        </div>
    </div>

    @if($document->intro_text)
        <p style="font-size: 10px; color: #64748b; font-style: italic; margin-bottom: 20px;">{{ $document->intro_text }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 10%;">Type</th>
                <th style="width: 40%;">D&eacute;signation</th>
                <th class="text-center" style="width: 8%;">Qt&eacute;</th>
                <th class="text-right" style="width: 14%;">Prix unit. HT</th>
                <th class="text-center" style="width: 8%;">TVA</th>
                <th class="text-right" style="width: 20%;">Total HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->items as $idx => $item)
                <tr style="{{ $idx % 2 === 1 ? 'background: #f8fafc;' : 'background: #fff;' }}">
                    <td>{{ $item->product_type ?? 'Service' }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }} DH</td>
                    <td class="text-center">{{ $item->tax_rate }}%</td>
                    <td class="text-right" style="font-weight: 600;">{{ number_format($item->total_ht, 2) }} DH</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td style="font-weight: 600; color: {{ $primaryColor }};">Total HT</td>
                <td class="text-right" style="font-weight: 600;">{{ number_format($document->total_ht, 2) }} DH</td>
            </tr>
            @if($document->global_discount_amount > 0)
            <tr>
                <td style="color: #ef4444;">Remise ({{ $document->global_discount_value }}%)</td>
                <td class="text-right" style="color: #ef4444;">- {{ number_format($document->global_discount_amount, 2) }} DH</td>
            </tr>
            @endif
            <tr>
                <td style="font-weight: 600; color: {{ $primaryColor }};">TVA</td>
                <td class="text-right" style="font-weight: 600;">{{ number_format($document->total_tva, 2) }} DH</td>
            </tr>
            <tr class="total-row">
                <td>Total TTC</td>
                <td class="text-right">{{ number_format($document->total_ttc, 2) }} DH</td>
            </tr>
        </table>
    </div>

    @if($document->payment_condition || $document->payment_mode)
    <div style="margin-top: 30px; font-size: 10px;">
        <h3 style="color: {{ $primaryColor }}; text-transform: uppercase; letter-spacing: 1px; font-size: 10px; margin: 0 0 4px;">Conditions</h3>
        @if($document->payment_condition)<p style="margin: 2px 0;"><strong style="color: {{ $primaryColor }};">Conditions de r&egrave;glement :</strong> {{ $document->payment_condition }}</p>@endif
        @if($document->payment_mode)<p style="margin: 2px 0;"><strong style="color: {{ $primaryColor }};">Mode de r&egrave;glement :</strong> {{ $document->payment_mode }}</p>@endif
    </div>
    @endif

    @if($document->bankAccount)
    <div style="margin-top: 20px; padding: 12px; background: #f8fafc; border-radius: 6px; font-size: 10px;">
        <h3 style="color: {{ $primaryColor }}; text-transform: uppercase; letter-spacing: 1px; font-size: 10px; margin: 0 0 6px;">Coordonn&eacute;es bancaires</h3>
        <p style="margin: 2px 0;"><strong>Banque :</strong> {{ $document->bankAccount->bank_name }}</p>
        <p style="margin: 2px 0;"><strong>RIB :</strong> {{ $document->bankAccount->rib }}</p>
    </div>
    @endif

    @if($document->terms || $document->notes || $document->conclusion_text)
    <div class="notes">
        @if($document->notes)<p><strong>Notes :</strong> {{ $document->notes }}</p>@endif
        @if($document->terms)<p><strong>Conditions :</strong> {{ $document->terms }}</p>@endif
        @if($document->conclusion_text)<p>{{ $document->conclusion_text }}</p>@endif
    </div>
    @endif

    <div class="footer">
        Document g&eacute;n&eacute;r&eacute; le {{ now()->format('d/m/Y à H:i') }} &bull; {{ $document->company->company_name ?? config('app.name', 'Facturex') }}
    </div>
</body>
</html>