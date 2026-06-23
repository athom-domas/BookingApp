# Importazione business

Il comando `business:import` crea tutti i record necessari nel gestionale (business, profilo, staff, servizi, orari, media) partendo dal sito web del salone oppure da un file JSON compilato a mano.

---

## Workflow A — Salone con sito web

### Step 1 — Scraping + AI, senza toccare il DB

```bash
docker-compose run --rm app php artisan business:import https://www.nomsalone.it \
    --save-json=storage/imports/nomsalone.json \
    --dry-run
```

- Scarica homepage e fino a 5 pagine rilevanti
- Legge JSON-LD/schema.org se presente (priorità alta)
- Chiama Claude Haiku per estrarre i dati
- Salva il JSON e mostra anteprima — **non crea nulla nel DB**

Il file JSON può essere corretto a mano prima del secondo step.

### Step 2 — Import nel DB

```bash
docker-compose run --rm app php artisan business:import https://www.nomsalone.it \
    --from-json=storage/imports/nomsalone.json
```

---

## Workflow B — Salone senza sito web (JSON manuale)

Copia il template, compilalo e importa direttamente senza passare un URL:

```bash
cp storage/imports/_template.json storage/imports/nomsalone.json
# modifica nomsalone.json

docker-compose run --rm app php artisan business:import \
    --from-json=storage/imports/nomsalone.json
```

---

## Opzioni disponibili

| Opzione | Descrizione |
|---|---|
| `--business-id=N` | Aggiorna un business esistente invece di crearne uno nuovo |
| `--dry-run` | Mostra i dati estratti senza creare nulla |
| `--force` | Salta la conferma interattiva |
| `--no-media` | Non scarica immagini (logo, gallery, avatar) |
| `--save-json=path` | Salva il JSON estratto su file |
| `--from-json=path` | Importa da JSON già salvato, salta scraping e AI |

---

## Altri esempi

**Aggiorna business esistente (ID 5) senza ri-scaricare le immagini:**

```bash
docker-compose run --rm app php artisan business:import \
    --from-json=storage/imports/nomsalone.json \
    --business-id=5 \
    --no-media \
    --force
```

**Import silenzioso (no conferma interattiva):**

```bash
docker-compose run --rm app php artisan business:import \
    --from-json=storage/imports/nomsalone.json \
    --force
```

---

## Cosa viene creato

| Record | Note |
|---|---|
| `Business` | Subdomain auto-generato da `Str::slug(nome)`, con collision avoidance |
| `SalonProfile` | Nome, tagline, descrizione, indirizzo, telefono, orari, social, Google Maps embed |
| `User` (staff) | Email sintetica `nome@nomsalone.it`, password temporanea mostrata in output, `must_change_password = true` |
| `Service` | Nome, descrizione, durata, prezzo; associato a tutto lo staff |
| `AvailabilityRule` | Un record per giorno per ogni membro staff; supporta pausa pranzo |
| Logo | Media collection `logo` + `favicon` di SalonProfile |
| Gallery | Media collection `gallery` di SalonProfile (max 10) |
| Avatar staff | Media collection `avatar` dell'utente |

> I media vengono scaricati **dopo** la transazione DB.

---

## Struttura del file JSON

Vedere `storage/imports/_template.json` per il template completo da compilare a mano.

```json
{
  "business": {
    "name": "Nome Salone",
    "tagline": null,
    "description": null,
    "address": "Via Roma 1, 00100 Roma RM",
    "phone": "+39 06 1234567",
    "whatsapp_number": null,
    "instagram_url": null,
    "facebook_url": null,
    "tiktok_url": null,
    "logo_url": null,
    "google_maps_embed": null
  },
  "hours": [
    { "day": "monday", "open": "09:00", "close": "19:30", "open_2": null, "close_2": null }
  ],
  "services": [
    { "name": "Taglio uomo", "description": null, "duration_minutes": 30, "price": 15.00 }
  ],
  "staff": [
    { "name": "Mario Rossi", "role": "admin", "bio": null, "photo_url": null }
  ],
  "gallery_images": []
}
```

**Note:**
- Giorni chiusi: ometti la riga dall'array `hours`
- Pausa pranzo: imposta `open_2` e `close_2`
- `role`: `"admin"` per il titolare, `"staff"` per i dipendenti
- `google_maps_embed`: incolla il valore `src` dell'iframe di Google Maps (non l'URL della pagina)

---

## Prerequisiti

- `ANTHROPIC_API_KEY` nel file `.env` (necessaria solo per il workflow A con scraping)
- Docker in esecuzione (`docker-compose up -d`)
