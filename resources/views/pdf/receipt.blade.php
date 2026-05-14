<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reçu de vote</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; margin: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #00d991 0%, #0da876 100%); color: white; padding: 30px; text-align: center; }
        .logo { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .subtitle { font-size: 13px; opacity: 0.95; }
        .content { padding: 40px; }
        .title { font-size: 20px; font-weight: 600; color: #00d991; margin-bottom: 30px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-weight: 600; }
        .section-content { font-size: 16px; color: #333; }
        .candidate-box { background: #f9f9f9; border-left: 4px solid #00d991; padding: 15px; border-radius: 6px; margin-top: 12px; }
        .candidate-info { display: flex; gap: 15px; }
        .candidate-photo { width: 80px; height: 100px; object-fit: cover; border-radius: 6px; background: #e0e0e0; flex-shrink: 0; }
        .candidate-details { flex: 1; }
        .candidate-name { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 5px; }
        .candidate-position { font-size: 13px; color: #666; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .info-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 14px; color: #333; font-weight: 500; }
        .qr-section { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
        .ref-code { font-family: 'Courier New', monospace; font-size: 14px; font-weight: bold; letter-spacing: 2px; color: #00d991; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #f0f0f0; }
        .footer-text { margin: 5px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">⚡ CTS Vote</div>
            <div class="subtitle">Cyber Tech Squad - Système de Scrutin Sécurisé</div>
        </div>
        
        <div class="content">
            <div class="title">{{ $election }}</div>
            
            <div class="section">
                <div class="section-title">Votre vote</div>
                <div class="candidate-box">
                    <div class="candidate-info">
                        @if(isset($photo_path) && $photo_path)
                            <img src="{{ $photo_path }}" alt="Photo du candidat" class="candidate-photo">
                        @else
                            <div class="candidate-photo"></div>
                        @endif
                        <div class="candidate-details">
                            <div class="candidate-name">{{ $candidat_name ?? 'Non spécifié' }}</div>
                            <div class="candidate-position">Poste: {{ $election }}</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="info-row">
                    <span class="info-label">Électeur</span>
                    <span class="info-value">{{ $electeur }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date du vote</span>
                    <span class="info-value">{{ $date }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Référence de vote</span>
                    <span class="info-value">{{ $ref }}</span>
                </div>
            </div>
            
            <div class="qr-section">
                <div style="font-size: 12px; color: #999; margin-bottom: 8px;">CODE DE RÉFÉRENCE</div>
                <div class="ref-code">{{ $ref }}</div>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-text">Ce reçu est votre preuve de vote sur la plateforme CTS.</div>
            <div class="footer-text">Document généré automatiquement par le système de vote sécurisé.</div>
            <div class="footer-text">© 2026 Cyber Tech Squad - Tous droits réservés</div>
        </div>
    </div>
</body>
</html>