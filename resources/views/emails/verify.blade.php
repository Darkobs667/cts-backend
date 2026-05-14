<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 12px; padding: 40px; }
        .logo { text-align: center; font-size: 20px; font-weight: bold; color: #00d991; margin-bottom: 24px; }
        p { color: #444; line-height: 1.6; }
        .btn { display: block; width: fit-content; margin: 32px auto; padding: 14px 32px; background: #00d991; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .footer { text-align: center; font-size: 12px; color: #aaa; margin-top: 32px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">⚡ CTS Vote</div>
        <p>Bonjour,</p>
        <p>Merci de vous être inscrit. Cliquez sur le bouton ci-dessous pour vérifier votre adresse email et activer votre compte.</p>
        <a href="{{ $verificationUrl }}" class="btn">Vérifier mon email</a>
        <p>Ce lien expire dans <strong>24 heures</strong>. Si vous n'avez pas créé de compte, ignorez cet email.</p>
        <div class="footer">CTS Vote — Cyber Tech Squad</div>
    </div>
</body>
</html>
