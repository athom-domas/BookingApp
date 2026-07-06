# Guida test locale — WhatsApp (notifiche + AI booking)

Branch: `feature/whatsapp-ai-booking`. Due feature da provare: **notifiche operative** (conferma/annullo/spostamento/promemoria) e **AI booking conversazionale** (webhook + Claude).

## 0. Setup

```bash
git checkout feature/whatsapp-ai-booking
docker-compose up -d
docker-compose run --rm app php artisan migrate
# worker: DEVE consumare entrambe le code
docker-compose run --rm app php artisan queue:work --queue=whatsapp,default
```

`.env` — aggiungere (vedi `.env.example`):
```
ANTHROPIC_API_KEY=sk-ant-...        # solo per AI booking
ANTHROPIC_MODEL=claude-haiku-4-5
WHATSAPP_WEBHOOK_VERIFY_TOKEN=un-token-a-scelta
WHATSAPP_APP_SECRET=                # vuoto in locale = firma webhook non verificata (ok per test)
WHATSAPP_GRAPH_API_VERSION=v23.0
```

Strumenti: phpMyAdmin `:8080` (DB `booking_app`), Mailpit `:8025` (email fallback), app su `:8000`/nginx `:80`.

> **Nota Meta:** per invii VERI servono un numero WhatsApp Business su Meta for Developers (token + Phone Number ID) e template **approvati** con nomi `appointment_confirmed`, `appointment_cancelled`, `appointment_rescheduled` + il template promemoria configurato (default `appointment_reminder`). Senza credenziali reali puoi comunque testare tutto il flusso interno: i record vengono creati e il job marca `failed` (credenziali mancanti) — vedi step 4.

## 1. Gating superadmin (feature premium)

1. Login come `super_admin` su `/superadmin` → **Saloni**.
2. Sulla riga del salone di test → azione **WhatsApp**.
3. Attiva **Notifiche WhatsApp abilitate**, imposta **Limite mensile** (es. 5 per testare il limite). Vedi anche "Inviati questo mese".
4. Verifica in `integration_settings`: `whatsapp_notifications_enabled=1`, `whatsapp_monthly_limit=5`.

## 2. Credenziali tenant + stato

1. Login come `admin` del salone su `/admin/{subdomain}/integration-settings`.
2. Sezione WhatsApp: inserisci `meta_whatsapp_token` e `meta_whatsapp_phone_id` (reali o fittizi).
3. Controlla la sezione stato: "Notifiche WhatsApp (gestite dalla piattaforma)" = **abilitate** (sola lettura — il tenant NON può auto-abilitarsi), contatore "X / 5".

## 3. Preferenze cliente

Il cliente deve avere: `user_preferences.notification_channel = 'whatsapp'` e `phone_number` valorizzato (formato `+39...` o `333...`). Impostalo dal portale cliente o via phpMyAdmin/tinker.

## 4. Notifica di conferma (trigger principale)

1. Crea un appuntamento per quel cliente e portalo a stato `confirmed` (dal panel admin o dal flusso di prenotazione).
2. Verifica in `whatsapp_messages`: nuova riga con `direction=outbound`, `type=template`, `template_name=appointment_confirmed`, `status=queued`.
3. Col worker attivo: con credenziali reali → `status=sent` + `wamid` valorizzato (e messaggio sul telefono); con credenziali fittizie → `status=failed`, `error_message` valorizzato.
4. Idempotenza: ri-conferma lo stesso appuntamento → NON viene creata una seconda riga.

## 5. Annullamento e spostamento

- Annulla l'appuntamento → riga `appointment_cancelled`.
- Sposta un altro appuntamento (drag nel calendario o modifica orario) → riga `appointment_rescheduled` (parte DOPO il commit della transazione).

## 6. Promemoria con fallback email

1. Crea un `appointment_reminders` con `scheduled_for` nel passato e `status=pending` (o aspetta lo scheduler: gira ogni 5 min).
2. `docker-compose run --rm app php artisan schedule:run`
3. Se i gate WhatsApp passano → riga template promemoria; se bloccati (es. disabilitato, senza telefono, canale email) → **email su Mailpit** (`:8025`).

## 7. Limite mensile e reset

1. Con limite 5: manda notifiche finché `whatsapp_monthly_sent = 5` (o setta il valore a mano in `integration_settings`).
2. Prossimo trigger → nessuna riga creata, log "WhatsApp monthly limit reached" in `storage/logs/laravel.log`.
3. Reset: `docker-compose run --rm app php artisan whatsapp:reset-monthly-counters` → contatore a 0 (schedulato il 1° del mese).

## 8. Delivery status (con credenziali reali)

Dopo un invio `sent`, quando Meta manda lo status al webhook: righe in `whatsapp_message_statuses` collegate via `whatsapp_message_id`; uno status `failed` di Meta riporta la notifica a `status=failed`.

## 9. AI booking (webhook + Claude)

Serve un tunnel pubblico verso il locale:
```bash
ngrok http 80   # oppure cloudflared tunnel
```
1. Su Meta for Developers → App → WhatsApp → Configuration: webhook URL `https://<tunnel>/whatsapp/webhook`, verify token = `WHATSAPP_WEBHOOK_VERIFY_TOKEN`, subscribe a `messages`.
2. In `/admin/{subdomain}/integration-settings`: attiva **Assistente WhatsApp** (`whatsapp_ai_enabled`) e verifica `ANTHROPIC_API_KEY` nel `.env`.
3. Scrivi da un telefono al numero WhatsApp Business: l'AI risponde, propone slot, prenota (con conferma). Controlla `whatsapp_messages` (righe `inbound`) e il worker sulla coda `whatsapp`.

**Senza Meta/tunnel** puoi simulare l'inbound con curl (in locale la firma non è richiesta se `WHATSAPP_APP_SECRET` è vuoto):
```bash
curl -X POST http://localhost/whatsapp/webhook -H 'Content-Type: application/json' -d '{
  "entry": [{"changes": [{"value": {
    "metadata": {"phone_number_id": "IL_TUO_PHONE_ID"},
    "contacts": [{"wa_id": "393331234567", "profile": {"name": "Mario"}}],
    "messages": [{"id": "wamid.test1", "from": "393331234567", "type": "text",
                  "timestamp": "1751500000", "text": {"body": "Vorrei prenotare un taglio"}}]
  }}]}]}'
```
(`phone_number_id` deve corrispondere a `meta_whatsapp_phone_id` di un salone). La risposta dell'AI verso Meta fallirà con credenziali fittizie, ma vedi il flusso completo nei log e nel DB.

## 10. Checklist finale prima del merge

- [ ] Notifica conferma/annullo/spostamento creata e (con creds reali) consegnata
- [ ] Promemoria WhatsApp + fallback email su Mailpit
- [ ] Tenant non può auto-abilitarsi (solo placeholder read-only)
- [ ] Limite mensile blocca + comando reset funziona
- [ ] Idempotenza (no doppioni per stesso appuntamento+template)
- [ ] AI: conversazione risponde e propone slot (se testata)

**Post-merge (deploy):** abilitare il flag premium per i tenant che già usavano i promemoria WhatsApp (altrimenti degradano a email); worker con `--queue=whatsapp,default`; impostare `WHATSAPP_APP_SECRET` in produzione (senza, il webhook rifiuta tutto con 403).
