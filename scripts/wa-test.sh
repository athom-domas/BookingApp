#!/usr/bin/env bash
# Simula un messaggio WhatsApp inbound sul webhook del gestionale.
#
# Uso:
#   ./scripts/wa-test.sh <staging|prod> <messaggio> [opzioni]
#   ./scripts/wa-test.sh <staging|prod> --reset          # solo reset stato
#
# Opzioni:
#   -p, --phone PHONE   Numero mittente senza + (default: 393298826230)
#   -n, --name  NAME    Nome contatto (default: Daniele)
#   -r, --reset         Svuota lo stato conversazione prima di inviare
#   -l, --logs          Mostra log rilevanti dopo la risposta AI (~10s)
#   -h, --help          Mostra questo messaggio

set -euo pipefail

usage() {
  awk 'NR==1{next} /^#/{sub(/^# ?/,""); print; next} /^$/{next} {exit}' "$0"
  exit 0
}

[[ $# -lt 1 || "$1" == "-h" || "$1" == "--help" ]] && usage

ENV="$1"; shift

case "$ENV" in
  staging) BASE_URL="https://staging.booking-app.it"
           SSH_PATH="~/staging" ;;
  prod)    BASE_URL="https://booking-app.it"
           SSH_PATH="~/public_html" ;;
  *)       echo "Errore: env deve essere 'staging' o 'prod'" >&2; exit 1 ;;
esac

# Il messaggio è opzionale (es. quando si usa solo --reset)
MSG=""
if [[ $# -gt 0 && "${1:0:1}" != "-" ]]; then
  MSG="$1"; shift
fi

PHONE="393298826230"
NAME="Daniele"
RESET=false
SHOW_LOGS=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    -p|--phone) PHONE="${2#+}"; shift 2 ;;
    -n|--name)  NAME="$2"; shift 2 ;;
    -r|--reset) RESET=true; shift ;;
    -l|--logs)  SHOW_LOGS=true; shift ;;
    -h|--help)  usage ;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1 ;;
  esac
done

if [[ -z "$MSG" && "$RESET" == false ]]; then
  echo "Errore: specifica un messaggio oppure usa --reset" >&2
  exit 1
fi

SSH_HOST="su814880@access-5020661163.webspace-host.com"
NORM_PHONE="+${PHONE}"

# ── Reset stato conversazione ─────────────────────────────────────────────────

if [[ "$RESET" == true ]]; then
  if [[ "$ENV" == "prod" ]]; then
    echo "⚠  Reset non supportato in produzione" >&2; exit 1
  fi
  echo "→ reset conversazione ${NORM_PHONE}..."
  ssh "$SSH_HOST" "cd ${SSH_PATH} && php8.5 artisan cache:forget 'whatsapp:conv:1:${NORM_PHONE}'" 2>/dev/null \
    && echo "  ok" || echo "  (chiave non trovata)"
fi

[[ -z "$MSG" ]] && exit 0

# ── Invia messaggio ───────────────────────────────────────────────────────────

WAMID="wamid.test_$(date +%s%3N)"
TS=$(date +%s)

# Usa Python per generare JSON corretto (gestisce quoting e caratteri speciali)
PAYLOAD=$(python3 - "$NAME" "$PHONE" "$WAMID" "$TS" "$MSG" <<'PYEOF'
import json, sys
name, phone, wamid, ts, msg = sys.argv[1:]
print(json.dumps({
  "object": "whatsapp_business_account",
  "entry": [{"id": "1733207641024442", "changes": [{"value": {
    "messaging_product": "whatsapp",
    "metadata": {"display_phone_number": "15550402220", "phone_number_id": "1205511029309574"},
    "contacts": [{"profile": {"name": name}, "wa_id": phone}],
    "messages": [{"from": phone, "id": wamid, "timestamp": ts,
                  "text": {"body": msg}, "type": "text"}]
  }, "field": "messages"}]}]
}))
PYEOF
)

echo "→ [$ENV] ${NORM_PHONE} (${NAME}) → \"${MSG}\""
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST "${BASE_URL}/whatsapp/webhook" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD")
echo "← HTTP ${HTTP_CODE}"

# ── Log dopo risposta AI ──────────────────────────────────────────────────────

if [[ "$SHOW_LOGS" == true ]]; then
  echo "   attendo risposta AI (~10s)..."
  sleep 10
  echo "--- log ---"
  ssh "$SSH_HOST" "tail -20 ${SSH_PATH}/storage/logs/laravel.log" 2>/dev/null \
    | grep -E 'sendText|WhatsApp text|ERROR|Exception|WARN' \
    | tail -5 \
    || echo "   (nessuna riga rilevante)"
fi
