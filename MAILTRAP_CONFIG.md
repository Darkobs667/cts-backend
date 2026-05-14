# Configuration Mailtrap pour CTS Vote

## Variables d'environnement (.env)

Ajouter ou modifier ces variables dans le fichier `.env` du backend:

```env
MAIL_MAILER=smtp
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=<votre_username_mailtrap>
MAIL_PASSWORD=<votre_password_mailtrap>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@uadb.edu.sn
MAIL_FROM_NAME="CTS Vote"
```

## Obtenir les identifiants Mailtrap

1. Rendez-vous sur [https://mailtrap.io](https://mailtrap.io)
2. Créez un compte ou connectez-vous
3. Allez dans **Email Testing** → **Integration**
4. Sélectionnez **Laravel 9+** comme framework
5. Copiez les valeurs pour:
   - `MAIL_USERNAME` (API token)
   - `MAIL_PASSWORD` (API secret)
   - `MAIL_HOST` (généralement `live.smtp.mailtrap.io`)
   - `MAIL_PORT` (généralement `587`)

## Template d'email

Le template de vérification d'email se trouve dans:
- Backend: `resources/views/emails/verify.blade.php`

Les templates PDF (reçu) se trouvent dans:
- Backend: `resources/views/pdf/receipt.blade.php`

## Test d'envoi

Pour tester l'envoi d'email en local, vous pouvez:

### Option 1: Utiliser Mailtrap directement
- Les emails envoyés via Mailtrap arriveront dans l'inbox de test Mailtrap
- Consultable sur https://mailtrap.io → Email Testing

### Option 2: Utiliser le driver de log (développement)
```env
MAIL_MAILER=log
```
Les emails apparaîtront dans `storage/logs/laravel.log`

### Option 3: Tester via Tinker
```bash
php artisan tinker
```

Puis:
```php
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Mail;

Mail::to('test@example.com')->send(new VerifyEmail('token123', 'http://localhost:5173'));
```

## Vérification de l'intégration

1. Inscrivez-vous sur le frontend → un email devrait être envoyé
2. Vérifiez dans le dashboard Mailtrap que l'email arrive
3. Cliquez sur le lien de vérification
4. Connectez-vous et votez
5. Téléchargez un reçu PDF

## Emails envoyés par le système

- **Vérification d'email** (Registration):
  - Vue: `resources/views/emails/verify.blade.php`
  - Classe: `App\Mail\VerifyEmail`

- **Reçu de vote** (PDF):
  - Vue: `resources/views/pdf/receipt.blade.php`
  - Route: `/api/voter/receipt/{voteId}`

## Dépannage

### Les emails ne sont pas envoyés
- Vérifiez que `MAIL_MAILER` est configuré à `smtp`
- Vérifiez les identifiants Mailtrap
- Vérifiez la connexion réseau

### Le lien de vérification ne fonctionne pas
- Vérifiez que `FRONTEND_URL` dans `.env` est correct
- Format attendu: `http://localhost:5173` (sans slash à la fin)

### Les PDF ne s'affichent pas correctement
- Vérifiez que DomPDF est installé: `composer require barryvdh/laravel-dompdf`
- Vérifiez les permissions du dossier `storage/`
