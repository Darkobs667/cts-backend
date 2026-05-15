<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Résultats des scrutins</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #00d991; padding-bottom: 15px; }
        .header h1 { font-size: 20px; color: #00d991; margin: 0 0 5px; }
        .header p { color: #64748b; margin: 0; font-size: 10px; }
        .election { margin-bottom: 30px; page-break-inside: avoid; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
        .election-header { background: #f8fafc; padding: 10px 15px; border-bottom: 1px solid #e2e8f0; }
        .election-title { font-size: 14px; font-weight: bold; color: #0f172a; margin: 0 0 3px; }
        .election-meta { font-size: 9px; color: #64748b; }
        .election-body { padding: 10px 15px; }
        .totals { display: flex; gap: 20px; margin-bottom: 10px; font-size: 10px; color: #475569; }
        .totals span { background: #f1f5f9; padding: 3px 8px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 9px; color: #94a3b8; text-transform: uppercase; padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        .rank { color: #94a3b8; font-weight: bold; width: 20px; }
        .name { font-weight: bold; color: #0f172a; }
        .winner-row td { background: #f0fdf4; }
        .winner-name { color: #16a34a; }
        .bar-cell { width: 120px; }
        .bar-bg { background: #e2e8f0; border-radius: 3px; height: 6px; }
        .bar-fill { background: #00d991; border-radius: 3px; height: 6px; }
        .footer { text-align: center; margin-top: 30px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚡ Résultats des scrutins — CTS Vote</h1>
        <p>Cyber Tech Squad · Généré le {{ $generated_at }}</p>
    </div>

    @foreach($elections as $election)
        @php
            $maxVotes = collect($election['candidates'])->max('votes_count') ?: 1;
        @endphp
        <div class="election">
            <div class="election-header">
                <div class="election-title">{{ $election['title'] }}</div>
                <div class="election-meta">
                    Statut : {{ $election['is_active'] ? 'En cours' : 'Clôturé' }}
                    &nbsp;·&nbsp; Total : {{ $election['total_votes'] }} vote(s)
                    @if($election['online_total'] !== $election['total_votes'])
                        &nbsp;·&nbsp; En ligne : {{ $election['online_total'] }}
                        &nbsp;·&nbsp; Physique : {{ $election['physical_total'] }}
                    @endif
                </div>
            </div>
            <div class="election-body">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Candidat</th>
                            <th>En ligne</th>
                            <th>Physique</th>
                            <th>Total</th>
                            <th>%</th>
                            <th class="bar-cell">Progression</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($election['candidates'] as $i => $candidate)
                            @php
                                $pct = $election['total_votes'] > 0
                                    ? round(($candidate['votes_count'] / $election['total_votes']) * 100, 1)
                                    : 0;
                                $barWidth = $maxVotes > 0 ? round(($candidate['votes_count'] / $maxVotes) * 100) : 0;
                                $isWinner = $i === 0 && $candidate['votes_count'] > 0;
                            @endphp
                            <tr class="{{ $isWinner ? 'winner-row' : '' }}">
                                <td class="rank">{{ $i + 1 }}</td>
                                <td class="name {{ $isWinner ? 'winner-name' : '' }}">
                                    {{ $candidate['name'] }}
                                    @if($isWinner) ✓ @endif
                                </td>
                                <td>{{ $candidate['online_votes'] }}</td>
                                <td>{{ $candidate['physical_votes'] }}</td>
                                <td><strong>{{ $candidate['votes_count'] }}</strong></td>
                                <td>{{ $pct }}%</td>
                                <td class="bar-cell">
                                    <div class="bar-bg">
                                        <div class="bar-fill" style="width: {{ $barWidth }}%;"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <div class="footer">
        Document confidentiel — Plateforme de vote sécurisée CTS · © 2026 Cyber Tech Squad
    </div>
</body>
</html>
