# Importazione business

## Il salone ha un sito web

```bash
# 1. Scraping + AI → salva JSON (non tocca il DB)
docker-compose run --rm app php artisan business:import https://www.nomsalone.it \
    --save-json=storage/imports/nomsalone.json \
    --dry-run

# 2. Correggi storage/imports/nomsalone.json se necessario

# 3. Import nel DB
docker-compose run --rm app php artisan business:import \
    --from-json=storage/imports/nomsalone.json
```

---

## Il salone non ha un sito web

```bash
# 1. Copia il template e compilalo
cp storage/imports/_template.json storage/imports/nomsalone.json

# 2. Import nel DB
docker-compose run --rm app php artisan business:import \
    --from-json=storage/imports/nomsalone.json
```

---

## Aggiornare un business esistente

```bash
docker-compose run --rm app php artisan business:import \
    --from-json=storage/imports/nomsalone.json \
    --business-id=5 \
    --force
```

---

## Struttura JSON

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

- Giorni chiusi: ometti la riga da `hours`
- Pausa pranzo: usa `open_2` e `close_2`
- `role`: `"admin"` per il titolare, `"staff"` per i dipendenti
- `google_maps_embed`: valore `src` dell'iframe Google Maps
