<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->number ?? 'Document' }}</title>
    <style>
        body { margin: 0; padding: 20px; background-color: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #062121; }
    </style>
</head>
<body>
    <p>Bonjour,</p>

    <p>Veuillez trouver ci-joint le document <strong>{{ $document->number ?? 'sans numéro' }}</strong> envoyé par <strong>{{ $senderName }}</strong>.</p>

    @if ($customMessage)
        <p style="white-space: pre-line;">{{ $customMessage }}</p>
    @endif

    <p>
        <strong>Document :</strong> {{ $document->number ?? 'Brouillon' }}<br>
        <strong>Client :</strong> {{ $document->customer?->name ?? '—' }}<br>
        <strong>Total TTC :</strong> {{ number_format($document->total_ttc, 2) }} DH<br>
        <strong>Date :</strong> {{ $document->created_at?->format('d/m/Y') ?? '—' }}
    </p>

    <p>Le document PDF est joint à cet email.</p>

    <p style="margin-top: 24px; color: #94A3B8; font-size: 12px;">
        Ce message a été envoyé par <strong style="color: #062121;">{{ $senderName }}</strong> via {{ config('app.name', 'Facturex') }}.
    </p>
</body>
</html>