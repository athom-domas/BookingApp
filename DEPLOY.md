# Deploy — booking-app.it

Hosting: **IONOS Hosting Plus**
Dominio principale: `https://booking-app.it`
Admin salone: `https://{subdomain}.booking-app.it/admin`

---

## 1. Acquisto hosting e dominio (IONOS)

1. Registrarsi su [ionos.it](https://www.ionos.it)
2. Acquistare il pacchetto **Hosting Plus** (supporta PHP 8.4, MySQL 8, SSH, cron job)
3. Acquistare o trasferire il dominio `booking-app.it` nello stesso account
4. Dal pannello IONOS → **Hosting** → selezionare il pacchetto → **PHP** → impostare versione **8.4**
5. Dal pannello IONOS → **Hosting** → **SSH** → abilitare l'accesso SSH e annotare le credenziali

---

## 2. Configurazione DNS (IONOS)

Dal pannello IONOS → **Domini & SSL** → seleziona dominio → **DNS**:

### Record A (base)

| Tipo | Host | Valore |
|------|------|--------|
| A | @ | 217.160.0.129 |
| A | www | 217.160.0.129 |
| A | * | 217.160.0.129 |

Il record `*` copre il wildcard DNS (risoluzione) ma IONOS non supporta wildcard virtual host — i sottodomini vanno aggiunti manualmente (vedi sezione 8).

### Record email — deliverability (SPF / DKIM / DMARC)

Necessari per non finire nello spam. Da aggiungere nella stessa sezione DNS:

**SPF** (già presente di default su IONOS, verificare):
| Tipo | Host | Valore |
|------|------|--------|
| TXT | @ | `v=spf1 include:ionos.com ~all` |

**DKIM** (IONOS aggiunge automaticamente due chiavi — verificare che esistano):
| Tipo | Host |
|------|------|
| TXT | `s1-ionos._domainkey` |
| TXT | `s2-ionos._domainkey` |

Se mancano: pannello IONOS → **Email** → seleziona l'account → **Impostazioni DKIM** → attiva.

**DMARC**:
| Tipo | Host | Valore |
|------|------|--------|
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:info@booking-app.it` |

Se IONOS mostra un conflitto DMARC al salvataggio, scegliere **Standard** (non custom).

---

## 3. Accesso SSH

```bash
ssh su814880@access-5020661163.webspace-host.com
```

- Home directory: `/home/www` (document root configurata su IONOS panel: `/public`)
- PHP CLI: `/usr/bin/php8.5` (non usare `php` che punta a 8.3)
- Composer: non installato globalmente — usare `~/composer.phar`

```bash
# Esempio di utilizzo composer sul server
/usr/bin/php8.5 ~/composer.phar install --no-dev
```

---

## 4. Setup VS Code SFTP (per trasferimenti manuali)

Installare l'estensione **SFTP** (Natizyskunk) da VS Code Marketplace.

Creare `.vscode/sftp.json` (già in `.gitignore`):

```json
{
  "name": "IONOS Production",
  "host": "access-5020661163.webspace-host.com",
  "protocol": "sftp",
  "port": 22,
  "username": "su814880",
  "remotePath": "/home/www",
  "uploadOnSave": false,
  "ignore": [".git", "node_modules", ".env", "vendor", "storage/logs"]
}
```

Per i deploy automatici via terminale usare il `Makefile` (vedi sezione 5).

---

## 5. Deploy

Richiede `.env.production` compilato localmente (vedi sezione 6).

```bash
make deploy          # produzione: preflight + build + env + codice + vendor + cache + healthcheck
make deploy-staging  # staging: stesso flusso production-like, senza reset database

make deploy-env      # solo .env e .env.production → produzione
make deploy-assets   # solo build + asset pubblici → produzione
make deploy-code     # solo codice Laravel → produzione
make deploy-vendor   # solo composer install da composer.lock → produzione
```

Il Makefile:
- valida `composer.json` dentro Docker prima del deploy
- crea un lock remoto `.deploy.lock` per evitare deploy concorrenti
- manda l'app in maintenance mode durante sync/migrazioni/cache
- sincronizza codice Laravel, `bootstrap/`, `lang/`, `public/` e `public/build/`
- non copia `vendor/` dal locale: esegue sempre `composer install` sul server da `composer.lock`
- rimuove `public/hot` dal server per evitare asset Vite dev in produzione
- esegue healthcheck HTTP finale sugli URL configurati nel `Makefile`

Dopo ogni deploy il Makefile esegue automaticamente su server:
```bash
/usr/bin/php8.5 artisan optimize:clear
/usr/bin/php8.5 artisan migrate --force
/usr/bin/php8.5 artisan config:cache
/usr/bin/php8.5 artisan route:cache
/usr/bin/php8.5 artisan view:cache
/usr/bin/php8.5 artisan storage:link
/usr/bin/php8.5 artisan queue:restart
```

`make deploy-env` copia `.env.production` sia in `/home/www/.env` sia in `/home/www/.env.production`. Questo evita che Laravel carichi un vecchio `.env.production` quando l'ambiente PHP imposta `APP_ENV=production`.

Per resettare staging intenzionalmente:

```bash
make staging-reset-db
```

Il reset staging non è più parte di `make deploy-staging`: staging deve restare il più simile possibile alla produzione, salvo reset esplicito.

Se un deploy viene interrotto e resta un lock remoto, verificare prima che non ci sia davvero un deploy in corso, poi sbloccare con:

```bash
make deploy-unlock-prod
make deploy-unlock-staging
```

Per deploy di singoli file (rapido):
```bash
rsync -az --checksum app/Models/Foo.php su814880@access-5020661163.webspace-host.com:/home/www/app/Models/Foo.php
```

---

## 6. Environment

Crea `.env.production` in locale (già in `.gitignore`) con:

```env
APP_NAME="Booking App"
APP_ENV=production
APP_KEY=base64:...          # non rigenerare — usa quello esistente sul server
APP_DEBUG=false
APP_URL=https://booking-app.it
APP_BASE_DOMAIN=booking-app.it
APP_TIMEZONE=Europe/Rome

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
MAIL_USERNAME=info@booking-app.it      # deve essere un indirizzo email esistente su IONOS
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=info@booking-app.it
MAIL_FROM_NAME="Booking App"
MAIL_CONTACT_ADDRESS=info@booking-app.it

STRIPE_PUBLIC_KEY=
STRIPE_SECRET_KEY=
STRIPE_PRICE_ID=
STRIPE_BILLING_WEBHOOK_SECRET=
STRIPE_CONNECT_WEBHOOK_SECRET=
STRIPE_WEBHOOK_SECRET=

TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=
```

> **Nota MAIL_USERNAME**: deve essere un indirizzo email IONOS reale (creato da pannello → Email). Non usare indirizzi fantasiosi — IONOS rifiuta l'autenticazione SMTP con errore 550 se l'indirizzo non esiste sull'account.

> **Nota APP_TIMEZONE**: deve essere `Europe/Rome`. Il file `config/app.php` è già configurato per leggere questa variabile. Senza di essa i confronti di data/ora risultano sfasati di 2 ore rispetto all'ora italiana.

---

## 7. Setup iniziale (prima installazione)

```bash
# Sul server via SSH
cd /home/www

# Dipendenze PHP
/usr/bin/php8.5 ~/composer.phar install --no-dev

# Generare chiave app (solo al primissimo deploy, poi conservarla)
/usr/bin/php8.5 artisan key:generate

# Database
/usr/bin/php8.5 artisan migrate --force

# Storage
/usr/bin/php8.5 artisan storage:link
chmod -R 775 ~/storage/
chmod -R 775 ~/bootstrap/cache/

# Cache
/usr/bin/php8.5 artisan optimize:clear
```

---

## 8. Aggiungere un nuovo salone (subdomain)

IONOS non supporta sottodomini wildcard via pannello. Ogni nuovo salone richiede:

1. **IONOS panel → Sottodomini** → aggiungi `{subdomain}.booking-app.it` con document root `/public`
2. **IONOS panel → SSL** → assegna certificato gratuito Let's Encrypt al sottodominio (può richiedere qualche minuto)
3. Creare il business nel DB tramite il pannello admin di un salone esistente, oppure via tinker:
   ```bash
   /usr/bin/php8.5 artisan tinker
   ```
4. Estendere il trial se Stripe non è ancora configurato:
   ```bash
   /usr/bin/php8.5 artisan tinker --execute="\App\Models\Business::where('subdomain', '{subdomain}')->first()->update(['trial_ends_at' => now()->addDays(30)]);"
   ```

---

## 9. Cron job (reminder e scheduler)

Il scheduler Laravel deve girare ogni minuto. Su IONOS non è disponibile `crontab` da SSH — va configurato dal pannello.

**IONOS panel → Hosting → Cron Jobs → Crea cronjob**:

| Campo | Valore |
|-------|--------|
| Tipo | **UnixCron** |
| Comando | `/usr/bin/php8.5 /home/www/artisan schedule:run` |
| Intervallo | Avanzato — tutti i campi `*` |

Questo attiva l'invio automatico dei reminder email agli appuntamenti.

---

## Problemi noti e fix applicati

**Stripe non configurato**: se `STRIPE_SECRET_KEY` è vuota o la config cache è stata generata da un env sbagliato, Stripe Connect solleva `Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.`. Verificare:
```bash
/usr/bin/php8.5 artisan tinker --execute="var_export(['stripe_secret' => config('services.stripe.secret') ? 'set' : 'empty', 'cashier_secret' => config('cashier.secret') ? 'set' : 'empty', 'cached' => app()->configurationIsCached() ? 'yes' : 'no']);"
```
Se risulta `empty`, correggere `.env.production`, rieseguire `make deploy-env`, poi sul server:
```bash
/usr/bin/php8.5 artisan optimize:clear
/usr/bin/php8.5 artisan config:cache
```

**PHP su SSH**: usare sempre `/usr/bin/php8.5`, non `php` (punta a 8.3). Il Makefile usa l'alias `php85` — se il server cambia configurazione, verificare.

**Email CSS rotto su mobile**: i client email (Gmail) strippano i tag `<style>`. Il pacchetto `fedeisas/laravel-mail-css-inliner` risolve il problema inlining automaticamente il CSS nelle email.

**QUEUE_CONNECTION=sync**: i job girano in modo sincrono. Eccezioni dentro `DB::transaction()` durante l'invio email causano rollback dell'intera transazione. La logica di dispatch delle email è stata spostata fuori dalla transaction e wrappata in try/catch.

**Timezone**: le date sono salvate in ora italiana (Europe/Rome). `APP_TIMEZONE` deve essere `Europe/Rome` nel `.env` e `config/app.php` deve leggere `env('APP_TIMEZONE', 'UTC')`. Senza questa configurazione i confronti `now() < scheduled_date` risultano sfasati.

**Sottodomini e SSL**: i certificati SSL su IONOS per i sottodomini vanno assegnati manualmente uno per uno dal pannello. Non esiste wildcard SSL automatico su Hosting Plus.

**Email contatti**: arrivano all'indirizzo in `MAIL_CONTACT_ADDRESS`. Se non impostato, fallback su `MAIL_FROM_ADDRESS`.

**Composer sul server**: non è installato globalmente. Usare `~/composer.phar` (già presente in home). Se mancasse: scaricarlo con `curl -sS https://getcomposer.org/installer | /usr/bin/php8.5`.
