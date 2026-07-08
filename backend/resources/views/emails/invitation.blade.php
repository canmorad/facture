<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation - {{ $appName }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #F4F7F7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
        .header { background: linear-gradient(135deg, #062121 0%, #0a3a3a 100%); padding: 40px 30px 32px; text-align: center; }
        .header-logo { height: 52px; width: auto; margin-bottom: 16px; border-radius: 8px; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0 0 4px; font-weight: 700; letter-spacing: -0.3px; }
        .header .subtitle { color: rgba(255,255,255,0.7); font-size: 13px; margin: 0; }
        .body { padding: 40px 30px; }
        .body p { color: #334155; font-size: 15px; line-height: 1.7; margin: 0 0 20px; }
        .body .greeting { font-size: 16px; font-weight: 600; color: #062121; margin-bottom: 20px; }
        .icon-circle { display: inline-block; width: 20px; height: 20px; border-radius: 50%; text-align: center; line-height: 20px; font-size: 10px; font-weight: bold; margin-right: 6px; vertical-align: middle; }
        .icon-circle.green { background: #C5F82A; color: #062121; }
        .icon-circle.blue { background: #3B82F6; color: #fff; }
        .button { display: inline-block; background: linear-gradient(180deg, #C5F82A 0%, #b8e626 100%); color: #062121; text-decoration: none; padding: 14px 36px; border-radius: 12px; font-weight: 700; font-size: 15px; margin: 24px 0; box-shadow: 0 4px 14px rgba(197,248,42,0.35); }
        .info-box { background: #F8FAFC; border-radius: 10px; padding: 18px 20px; margin: 20px 0; }
        .info-box .info-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .info-box .info-row:last-child { margin-bottom: 0; }
        .info-box .info-label { font-size: 12px; color: #94A3B8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; min-width: 55px; }
        .info-box .info-value { font-size: 14px; color: #062121; font-weight: 600; }
        .footer { background: #F8FAFC; padding: 24px 30px; text-align: center; border-top: 1px solid #E2E8F0; }
        .footer p { color: #94A3B8; font-size: 12px; margin: 4px 0; line-height: 1.5; }
        .footer .app-name { color: #062121; font-weight: 700; }
        .expiry-note { font-size: 13px; color: #94A3B8; background: #FFFDF5; border: 1px solid #FDE68A; border-radius: 8px; padding: 10px 14px; margin-top: 16px; }
        .expiry-note strong { color: #92400E; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ $companyName }}</h1>
        <p class="subtitle">Invitation &agrave; rejoindre la plateforme</p>
    </div>
    <div class="body">
        <p class="greeting">Bonjour,</p>
        <p>Vous avez &eacute;t&eacute; invit&eacute;(e) &agrave; rejoindre <strong>{{ $companyName }}</strong> sur notre plateforme de facturation <strong>{{ $appName }}</strong>.</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $email }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Entreprise</span>
                <span class="info-value">{{ $companyName }}</span>
            </div>
        </div>

        @if ($isExistingUser)
            <p>
                <span class="icon-circle green">&#10003;</span>
                Comme vous poss&eacute;dez d&eacute;j&agrave; un compte, vous avez &eacute;t&eacute; <strong>automatiquement ajout&eacute;</strong> &agrave; l'entreprise
                <strong>{{ $companyName }}</strong>.
            </p>
            <p>Connectez-vous d&egrave;s maintenant pour acc&eacute;der &agrave; votre espace et commencer &agrave; collaborer.</p>
        @else
            <p>Pour finaliser votre inscription et acc&eacute;der &agrave; la plateforme, veuillez cliquer sur le bouton ci-dessous&nbsp;:</p>
            <a href="{{ $acceptUrl }}" class="button">
                <span class="icon-circle" style="background:#062121;color:#C5F82A;">&#10003;</span>
                Finaliser mon inscription
            </a>
            <div class="expiry-note">
                Ce lien expire dans <strong>48 heures</strong>.
            </div>
        @endif
    </div>
    <div class="footer">
        <p>Envoy&eacute; par <span class="app-name">{{ $appName }}</span> &middot; {{ $fromEmail }}</p>
        <p>&copy; {{ date('Y') }} {{ $appName }}. Tous droits r&eacute;serv&eacute;s.</p>
    </div>
</div>
</body>
</html>