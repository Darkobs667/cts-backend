<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Résultats des scrutins</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .election {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        .status {
            font-size: 10px;
            color: #666;
            margin-bottom: 10px;
        }
        .candidate {
            margin-left: 20px;
            margin-bottom: 8px;
        }
        .votes {
            font-weight: normal;
            color: #444;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 10px;
            color: #888;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Résultats des scrutins</h1>
        <p>Généré le {{ $generated_at }}</p>
    </div>

    @foreach($elections as $election)
        <div class="election">
            <div class="title">{{ $election['title'] }}</div>
            <div class="status">Statut : {{ $election['is_active'] ? 'En cours' : 'Clôturé' }}</div>
            <div>Total des votes : {{ $election['total_votes'] }}</div>
            @foreach($election['candidates'] as $candidate)
                <div class="candidate">
                    - {{ $candidate['name'] }} : {{ $candidate['votes_count'] }} voix
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="footer">
        Document confidentiel – Application de vote CTS
    </div>
</body>
</html>