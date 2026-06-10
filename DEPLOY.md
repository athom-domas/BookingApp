# Deploy — booking-app.it

Hosting: **IONOS Hosting Plus**
Dominio principale: `https://booking-app.it`
Admin salone: `https://{subdomain}.booking-app.it/admin`

---

## Accesso server

```bash
ssh su814880@access-5020661163.webspace-host.com
```

- Home directory: `~` (path assoluto: `/home/www`)
- Document root configurata su IONOS panel: `/public`
- PHP web server: 8.4
- PHP da SSH: `php85` (alias — non usare `php` che è 8.3)

---

## Deploy

Richiede `.env.production` compilato localmente (vedi sezione Environment).

```bash
make deploy          # tutto: env + assets + codice + cache clear
make deploy-env      # solo .env → server
make deploy-assets   # solo build CSS/JS (npm build + rsync)
make deploy-code     # solo PHP (app/, routes/, config/, resources/)
```

Dopo ogni deploy il Makefile esegue automaticamente su server:
```
php85 artisan config:clear && php85 artisan route:clear && php85 artisan view:clear
```

---

## Setup iniziale (una tantum)

Operazioni da fare solo al primo deploy o dopo un reset completo:

```bash
# Sul server via SSH
php85 artisan migrate --force
php85 artisan storage:link
chmod -R 775 ~/storage/
chmod -R 775 ~/bootstrap/cache/
```

---

## Environment

Crea `.env.production` in locale (già in `.gitignore`) con:

```env
APP_NAME="Booking App"
APP_ENV=production
APP_KEY=base64:...          # non rigenerare — usa quello esistente sul server
APP_DEBUG=false
APP_URL=https://booking-app.it
APP_BASE_DOMAIN=booking-app.it

APP_LOCALE=it
APP_FALLBACK_LOCALE=it
APP_FAKER_LOCALE=it_IT

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=db5020661674.hosting-data.io
DB_PORT=3306
DB_DATABASE=dbs15773724
DB_USERNAME=dbu2444809
DB_PASSWORD=...

CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

MAIL_MAILER=smtp
MAIL_HOST=smtp.ionos.it
MAIL_PORT=587
MAIL_USERNAME=noreply@booking-app.it
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@booking-app.it
MAIL_FROM_NAME="Booking App"
MAIL_CONTACT_ADDRESS=info@booking-app.it

STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=
STRIPE_PRICE_ID=
STRIPE_BILLING_WEBHOOK_SECRET=

TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
```

---

## DNS (IONOS)

| Tipo | Host | Valore |
|------|------|--------|
| A | @ | 217.160.0.129 |
| A | www | 217.160.0.129 |
| A | * | 217.160.0.129 |

Il record `*` copre i wildcard DNS, ma IONOS non supporta wildcard virtual host nel pannello.

---

## Aggiungere un nuovo salone (subdomain)

IONOS non supporta sottodomini wildcard via pannello. Ogni nuovo salone richiede:

1. **IONOS panel → Sottodomini** → aggiungi `{subdomain}.booking-app.it` con document root `/public`
2. **IONOS panel → SSL** → assegna certificato gratuito Let's Encrypt al sottodominio
3. Estendere il trial del business se Stripe non è ancora configurato:
   ```bash
   php85 artisan tinker --execute="\App\Models\Business::where('subdomain', '{subdomain}')->first()->update(['trial_ends_at' => now()->addDays(30)]);"
   ```

---

## Problemi noti

**Stripe non configurato**: se `STRIPE_SECRET_KEY` è vuota, il middleware `CheckSubscription` crasha con 500 sull'admin panel. Fix temporaneo: estendere il trial (vedi sopra).

**PHP su SSH**: usare sempre `php85` non `php`. Il Makefile usa SSH per i comandi artisan post-deploy — se il server cambia configurazione verificare l'alias.

**Sottodomini e SSL**: i certificati SSL su IONOS per i sottodomini vanno assegnati manualmente uno per uno dal pannello. Non esiste wildcard SSL automatico su Hosting Plus.

**Email contatti**: arrivano all'indirizzo in `MAIL_CONTACT_ADDRESS`. Se non impostato, fallback su `MAIL_FROM_ADDRESS`.
