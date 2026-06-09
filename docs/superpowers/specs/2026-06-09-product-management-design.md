# Gestione Prodotti — Design Spec

**Data:** 2026-06-09  
**Scope:** Catalogo prodotti retail (sciampo, lozioni, ecc.) con vendita online e ritiro in salone

---

## Contesto

I saloni/barberie necessitano di gestire la vendita di prodotti retail ai clienti. Il sistema dovrà supportare: catalogo prodotti nell'area admin, acquisto da parte dei clienti nel portale, gestione scorte con notifica soglia, e pagamento tramite gli stessi metodi già configurati per gli appuntamenti.

---

## Approccio scelto

**Standalone semplice (Approccio A)**: modelli dedicati `Product`, `ProductOrder`, `ProductOrderItem` senza dipendenze dal modello `Payment` esistente (che resta legato agli appuntamenti). Nessuna spedizione — solo ritiro in salone.

---

## Modelli dati

### Tabella `products`

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint | |
| business_id | bigint FK | tenant |
| name | string | |
| description | text nullable | |
| price | decimal(10,2) | |
| stock | int | decrementato ad ogni ordine |
| low_stock_threshold | int nullable | null = nessuna notifica |
| in_sale | boolean | visibile nel portale clienti |
| active | boolean | |
| timestamps | | |

Media: Spatie MediaLibrary, collection `photo`, disco `public`, conversione `thumb`. Nessuna colonna aggiuntiva.

### Tabella `product_orders`

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint | |
| business_id | bigint FK | tenant |
| user_id | bigint FK | cliente |
| status | enum | `pending`, `confirmed`, `ready`, `completed`, `cancelled` |
| payment_method | enum | `stripe`, `cash` |
| stripe_payment_intent_id | string nullable | |
| payment_status | enum | `pending`, `paid`, `cancelled`, `refunded` |
| notes | text nullable | |
| timestamps | | |

### Tabella `product_order_items`

| Colonna | Tipo | Note |
|---|---|---|
| id | bigint | |
| order_id | bigint FK | |
| product_id | bigint FK | |
| quantity | int | |
| unit_price | decimal(10,2) | congelato al momento dell'acquisto |
| timestamps | | |

### Aggiunta a `system_settings`

| Colonna | Tipo | Note |
|---|---|---|
| low_stock_notify_user_ids | json nullable | array di user IDs (admin + staff) |

---

## Order status lifecycle

```
pending → confirmed → ready → completed
                    ↘
                     cancelled (ripristina stock)
```

- `pending`: ordine creato, in attesa di pagamento Stripe (solo se payment_method = stripe)
- `confirmed`: pagamento ricevuto (Stripe) oppure ordine contanti accettato
- `ready`: salone ha preparato l'ordine, pronto per il ritiro
- `completed`: cliente ha ritirato
- `cancelled`: annullato (da admin o per pagamento fallito) — stock ripristinato

---

## Admin panel (Filament)

### `ProductResource`

- Voce "Prodotti" nel menu laterale
- **Lista**: foto (thumb), nome, prezzo, stock, badge rosso se sotto soglia, toggle in_sale, toggle active
- **Form**: nome, descrizione, prezzo, stock (campo numerico editabile per carico manuale magazzino), low_stock_threshold, upload foto singola, toggle in_sale, toggle active
- **Filtri**: attivi, in vendita, sotto soglia (`stock <= low_stock_threshold`)

### `ProductOrderResource`

- Voce "Ordini prodotti" nel menu laterale
- **Lista**: cliente, data, totale, stato, metodo pagamento
- **Dettaglio**: articoli + quantità + prezzi unitari + totale; pulsante avanza stato (confirmed → ready → completed); pulsante cancella (ripristina stock)
- Nessuna modifica agli item dopo la creazione dell'ordine

### Impostazioni

Sezione aggiuntiva nella pagina impostazioni SystemSetting esistente:
- Campo multi-select "Notifica scorte basse a": mostra tutti gli admin e staff del business corrente; salva in `low_stock_notify_user_ids`

---

## Portal cliente

### `/portal/products` — Catalogo

- Griglia prodotti: foto, nome, prezzo, pulsante "Aggiungi al carrello"
- Visibili solo prodotti con `in_sale = true`, `active = true`
- Prodotti con `stock = 0`: mostrati con badge "Esaurito", pulsante disabilitato
- Carrello persistito in sessione

### `/portal/products/checkout` — Checkout

- Riepilogo articoli nel carrello + totale
- Scelta metodo pagamento condizionata da `SystemSetting::getPaymentMode()`:
  - `stripe_only`: solo Stripe (Payment Intent inline)
  - `cash_only`: solo contanti (pagamento al ritiro)
  - `both`: il cliente sceglie
- Conferma ordine → redirect a pagina conferma con numero ordine

### `/portal/orders` — Storico ordini

- Lista ordini del cliente con: data, totale, stato, dettaglio articoli
- Accessibile solo ai clienti autenticati

---

## Flusso pagamento

### Stripe

1. Al checkout, `ProductOrderController` crea l'ordine in stato `pending` e decrementa lo stock
2. Si crea un Payment Intent Stripe con metadata `{ payable_type: 'product_order', payable_id: <id> }`
3. `StripeWebhookController` intercetta `payment_intent.succeeded` → ordine passa a `confirmed`, `payment_status = paid`
4. Se `payment_intent.payment_failed` o scadenza: ordine cancellato, stock ripristinato

### Contanti

1. Al checkout, l'ordine viene creato direttamente in stato `confirmed`, `payment_status = pending`
2. Lo stock è decrementato immediatamente
3. Il pagamento avviene al ritiro — l'admin aggiorna lo stato manualmente

---

## Notifiche scorte basse

**Trigger**: dopo ogni decremento stock da un ordine, se il nuovo stock ≤ `low_stock_threshold` **e** il valore precedente era > `low_stock_threshold` (notifica una sola volta per evento di esaurimento, non ad ogni acquisto successivo mentre rimane sotto soglia).

**Destinatari**: utenti in `SystemSetting::low_stock_notify_user_ids`. Se null o vuoto, nessuna notifica.

**Canale**: email, via `SendLowStockNotificationJob` (pattern esistente del progetto).

**Contenuto email**: nome prodotto, stock attuale, soglia impostata, link diretto alla pagina prodotto in admin.

**Ripristino**: quando l'admin modifica manualmente lo stock portandolo sopra soglia, il ciclo di notifica si resetta (il prossimo calo sotto soglia notificherà di nuovo).

---

## Considerazioni tecniche

- `Product` usa trait `BelongsToBusiness` (global scope su `business_id`) come tutti i modelli tenant
- `ProductOrder` e `ProductOrderItem` usano `BelongsToBusiness`
- MediaLibrary su `Product`: stessa configurazione di `SalonProfile` (disco `public`, conversione `thumb`)
- Il webhook Stripe distingue appointment vs product_order tramite `metadata.payable_type` nel Payment Intent
- Nessuna modifica al modello `Payment` esistente — zero rischio di regressioni sul flusso appuntamenti
- **Eliminazione prodotti**: consentita solo se il prodotto non ha ordini associati; altrimenti solo disattivazione (`active = false`, `in_sale = false`). Nessun soft delete.
- **Validazione stock al checkout**: al momento dell'invio del form di checkout si verifica che lo stock attuale sia sufficiente per ogni articolo nel carrello; se non lo è, si mostra un errore e lo stock viene aggiornato nel carrello prima del pagamento.

---

## Fuori scope

- Spedizioni e gestione indirizzi
- Categorie prodotti
- Sconti/coupon specifici per prodotti
- Integrazione vendite prodotti nei report revenue esistenti (futuro)
- Acquisto prodotti senza autenticazione
