<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de vote</title>
    <style>
        body { font-family: sans-serif; padding: 40px; }
        .header { background: #10b981; color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .content { margin-top: 30px; }
        .info { margin: 20px 0; }
        .info strong { display: inline-block; width: 200px; }
        .footer { margin-top: 50px; font-size: 12px; color: gray; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reçu de vote - Cyber Tech Squad</h1>
    </div>
    <div class="content">
        <h2>{{ $election }}</h2>
        <div class="info"><strong>Électeur :</strong> {{ $electeur }}</div>
        <div class="info"><strong>Date du vote :</strong> {{ $date }}</div>
        <div class="info"><strong>Référence :</strong> {{ $ref }}</div>
    </div>
    <div class="footer">
        Document généré automatiquement par le système de vote sécurisé. Ce reçu ne constitue pas une preuve de vote en dehors de la plateforme.
    </div>
</body>
</html>