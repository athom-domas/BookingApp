---
name: GestionalePro
description: Software di gestione prenotazioni per saloni, barberie e centri estetici italiani
colors:
  terra-bruciata: "#C4714A"
  terra-chiara: "#F2DDD3"
  inchiostro-di-noce: "#1C1410"
  cenere-calda: "#7A6A60"
  lino-grezzo: "#FAF7F2"
  lino-scuro: "#F2EBE3"
  orlo-caldo: "#E5D8CF"
typography:
  display:
    fontFamily: "Cormorant Garamond, Georgia, serif"
    fontSize: "clamp(2.5rem, 7vw, 5rem)"
    fontWeight: 600
    lineHeight: 1.05
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "Cormorant Garamond, Georgia, serif"
    fontSize: "clamp(1.875rem, 4vw, 3rem)"
    fontWeight: 600
    lineHeight: 1.1
  title:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
  body:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.7
  label:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.4
  fine:
    fontFamily: "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1.5
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  full: "9999px"
spacing:
  xs: "8px"
  sm: "20px"
  md: "32px"
  lg: "80px"
  xl: "96px"
components:
  button-primary:
    backgroundColor: "{colors.terra-bruciata}"
    textColor: "{colors.lino-grezzo}"
    rounded: "{rounded.md}"
    padding: "14px 32px"
    typography: "{typography.label}"
  button-primary-hover:
    backgroundColor: "#b86440"
    textColor: "{colors.lino-grezzo}"
    rounded: "{rounded.md}"
    padding: "14px 32px"
  card-feature:
    backgroundColor: "{colors.lino-grezzo}"
    rounded: "{rounded.lg}"
    padding: "28px"
  input-default:
    backgroundColor: "{colors.lino-grezzo}"
    textColor: "{colors.inchiostro-di-noce}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  input-focus:
    backgroundColor: "{colors.lino-grezzo}"
    textColor: "{colors.inchiostro-di-noce}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
---

# Design System: GestionalePro

## 1. Overview

**Creative North Star: "L'Atelier Italiano"**

GestionalePro è lo strumento professionale di chi gestisce un mestiere con le mani. Il sistema visivo deve riflettere questa realtà: caldo senza essere decorativo, preciso senza essere freddo, italiano senza essere pittoresco. Come l'interno di una buona bottega, tutto ha una posizione, tutto ha uno scopo, niente è lì per fare scena.

La palette ruota attorno a Terra Bruciata come accento, Inchiostro di Noce come struttura, e Lino Grezzo come superficie. Il display serif (Cormorant Garamond) porta personalità e identità solo nei titoli; Instrument Sans gestisce tutto il resto con pulizia chirurgica. La proporzione è intenzionale: Cormorant è il volto del brand, Instrument Sans è la voce operativa.

Il sistema rifiuta esplicitamente: l'estetica SaaS generica 2024 (gradienti teal, blob animati, card grid uniformi, eyebrow su ogni sezione); l'impersonalità enterprise di Salesforce o HubSpot; la vacuità lifestyle di Fresha/Treatwell; e l'eccesso estetico dei brand luxury fashion, che distoglierebbe dall'utilità operativa del prodotto.

**Key Characteristics:**
- Serif display usato raramente, con peso — mai decorativamente
- Terracotta come accento, non come sfondo; presente su ≤15% di ogni schermata
- Sezioni alternate Lino Grezzo / Lino Scuro, non bianco/grigio freddo
- Dark surfaces (Inchiostro di Noce) riservate a hero e CTA finale — contrasto alto, intento chiaro
- Zero animazioni decorative; il movimento serve l'azione
- Bordi caldi (Orlo Caldo) ovunque, mai gray-100 neutro

## 2. Colors: La Palette della Bottega

Una palette da atelier italiano: calda, materica, non tinteggiata-dall'IA.

### Primary
- **Terra Bruciata** (`#C4714A` / oklch(57% 0.135 42)): l'accento del brand. Bottoni primari, link attivi, icone nelle card feature, cerchi degli step, CTA. Compare con misura, non come sfondo. La sua rarità è il punto.
- **Terra Chiara** (`#F2DDD3` / oklch(90% 0.030 42)): tinta pallida di Terra Bruciata, usata come background per container di icone (bg-terra-light), badge su sfondo scuro. Mai come superficie di pagina.

### Neutral (Dark)
- **Inchiostro di Noce** (`#1C1410` / oklch(11% 0.025 40)): il quasi-nero del brand. Background dell'hero e della CTA finale — non slate-900. Anche il token di testo primario. Warm, non neutro.
- **Cenere Calda** (`#7A6A60` / oklch(47% 0.020 50)): testo secondario, descrizioni, placeholder. Abbastanza scuro da reggere 4.5:1 su Lino Grezzo (contrasto ≈5.1:1 — verificato). Mai usato su superfici colorate.

### Neutral (Light)
- **Lino Grezzo** (`#FAF7F2` / oklch(97% 0.010 70)): superficie di pagina principale. Alternato con Lino Scuro nelle sezioni. Non bianco puro, non crema AI-default: il chroma 0.010 lo ancora al brand senza gridarlo.
- **Lino Scuro** (`#F2EBE3` / oklch(93% 0.015 65)): superficie alternata. Problem, Features, Pricing, FAQ. Crea ritmo di pagina senza ombre o divisori.
- **Orlo Caldo** (`#E5D8CF` / oklch(86% 0.018 55)): bordi di card, input, divisori. Sostituisce gray-100/200 ovunque nel progetto.

### Named Rules
**The Terra Bruciata Rule.** Terra Bruciata è l'unico accento. Non si mescola con altri colori vivaci. Appare su ≤15% di qualsiasi schermata. Se copre più superficie, il design è diventato uniform.

**The Warm-Only Rule.** Nessun token freddo (gray, slate, zinc, blue) sopravvive in nessuna superficie. Se un elemento usa gray-500 o slate-50, è un residuo non ancora migrato.

**The No-Gradient-Text Rule.** Il `background-clip: text` con gradient è vietato. I titoli usano un singolo colore solido. Se il testo gradiente è già presente come artifact (`.text-grad`), va rimosso o sostituito.

## 3. Typography

**Display Font:** Cormorant Garamond (weights 400, 600, 700; italic 400) — Google Fonts
**Body Font:** Instrument Sans (weights 400, 500, 600) — Bunny Fonts / Google Fonts

**Character:** Un contrasto di asse forte: un serif ottocentesco con grace italiana contro un sans-serif contemporaneo costruito per la chiarezza su schermo. Non competono: Cormorant porta l'identità, Instrument Sans porta la funzione.

### Hierarchy

- **Display** (Cormorant Garamond, 600, clamp(2.5rem–5rem), leading 1.05, tracking -0.01em): hero H1 e titoli di pagina standalone. La scala fluida evita overflow su mobile. Max font-size: 5rem / 80px — non si supera.
- **Headline** (Cormorant Garamond, 600, clamp(1.875rem–3rem), leading 1.1): H2 di sezione su landing e pagine marketing. Non su dashboard o form.
- **Title** (Instrument Sans, 600, 1.125rem / 18px, leading 1.4): titoli di card, titoli di section in app. Più vicino all'utente operativo.
- **Body** (Instrument Sans, 400, 1rem / 16px, leading 1.7): tutto il testo di paragrafo. Max-width 65–75ch. Non compresso sotto 15px.
- **Label** (Instrument Sans, 600, 0.875rem / 14px, leading 1.4): etichette di form, testi di bottone, voci di navigazione desktop, descrizioni brevi sotto statistiche.
- **Fine** (Instrument Sans, 400, 0.75rem / 12px, leading 1.5): fine print, date, note legali, timestamp. Non più piccolo di 12px mai.

### Named Rules
**The Serif-Ceiling Rule.** Cormorant non appare mai su schermi dell'app (pannello admin, dashboard, form operativi). Vive sulle superfici brand: landing, marketing, pagine legali. Il confine è netto.

**The Balance Rule.** Ogni H1–H3 deve dichiarare `text-wrap: balance`. Ogni paragrafo lungo `text-wrap: pretty`. Non opzionale: orphans e overflow su tablet sono errori di produzione.

## 4. Elevation

GestionalePro è **flat by default con un'eccezione strutturale**. Le superfici sono piatte a riposo. La profondità è comunicata attraverso l'alternanza cromatica (Lino Grezzo ↔ Lino Scuro) e i bordi caldi (Orlo Caldo), non attraverso ombre.

L'unica eccezione intenzionale è la **pricing card**: `shadow-xl shadow-ink/10` — un'ombra con colore brand diluito, che segnala che questa è la superficie più importante della pagina. È strutturale, non decorativa.

### Shadow Vocabulary

- **Elevated-CTA** (`0 20px 50px rgba(28, 20, 16, 0.10)`): riservato alla pricing card e ai modal. Una sola volta per schermata. Il colore brand nell'ombra la distingue dall'ombra generica.
- **Hover-lift** (`0 4px 16px rgba(28, 20, 16, 0.08)`): hover sulle feature card. Appaiono solo come risposta allo stato, non al riposo.
- **Assente** (la regola predefinita): header sticky, nav, card normali, input — tutti senza ombra.

### Named Rules
**The Flat-by-Default Rule.** Le ombre appaiono solo come risposta allo stato (hover, focus, elevation deliberata). Una pagina senza ombre è corretta. Una pagina piena di ombre è rotta.

**The Warm-Shadow Rule.** Quando un'ombra è necessaria, usa `rgba(28, 20, 16, X)` — Inchiostro di Noce come colore ombra, non nero neutro o rgba(0,0,0,X). Il brand è presente anche nelle ombre.

## 5. Components

### Buttons

Il bottone primario porta tutta l'identità del brand. Gli altri restano in ombra finché servono.

- **Shape:** bordi gently curved (12px radius / rounded-xl)
- **Primary:** Terra Bruciata su Lino Grezzo (#C4714A bg, #FAF7F2 text). Padding 14px 32px. Font Label (Instrument Sans 600 14px). Shimmer effect al hover: sweep di luce bianca semi-trasparente da sx a dx in 0.6s.
- **Primary Hover:** `bg-terra/85` — 15% più scuro senza cambiare hue. Nessun cambio di scala o translate.
- **Danger / Destructive:** `bg-red-600` con stessa forma, usato solo in contesti admin (elimina, cancella).
- **Ghost (nav):** nessun background, testo in Cenere Calda, hover in Inchiostro. Usato nella navigazione desktop.
- **Focus:** `ring-2 ring-terra/40 ring-offset-2` — visibile, non invadente.

### Cards / Containers

Card come contenitori semantici, non come decorazione di default.

- **Corner style:** Gently rounded (16px / rounded-2xl) per le card principali; 12px per i container di form.
- **Background:** Lino Grezzo su sezioni Lino Scuro (o viceversa — sempre in contrasto con la sezione).
- **Shadow strategy:** Nessuna ombra a riposo. Hover lift (4px, ink/8) sulle feature card.
- **Border:** Orlo Caldo (`border border-warm-border`) — sempre presente, non opzionale.
- **Internal padding:** 28px (p-7) per card content, 32px (p-8) per card CTA.
- **Proibito:** card annidate. Una card dentro una card è sempre un errore di struttura.

### Inputs / Fields

Form come spazi di lavoro, non come display. La chiarezza sopra tutto.

- **Style:** outline con bordo Orlo Caldo (`border border-warm-border`), background Lino Grezzo, 12px radius.
- **Focus:** `focus:border-terra focus:ring-2 focus:ring-terra/20` — Terra Bruciata come colore di focus per coerenza con l'accento.
- **Error:** `border-red-300 bg-red-50` — rosso per gli errori è semanticamente corretto e non conflittuosa con la palette.
- **Label:** Instrument Sans 600 14px, Inchiostro di Noce. Non Cenere Calda: le label devono essere lette a colpo d'occhio.
- **Placeholder:** Cenere Calda. Contrasto 4.5:1 su Lino Grezzo — verificato.
- **Select:** stesso trattamento degli input. Background bianco (o Lino Grezzo) per compatibilità browser.

### Navigation

Due stati distinti: trasparente sull'hero scuro, cream sulla pagina.

- **Transparent (hero):** logo Cormorant in bianco + terra, link bianco/80, hamburger bianco.
- **Scrolled:** `bg-cream/95 backdrop-blur-sm` con bordo Orlo Caldo sotto. Logo in Inchiostro + Terra, link in Cenere Calda, hover Inchiostro.
- **CTA button:** sempre Terra Bruciata, mai ghost in nav.
- **Mobile drawer:** background Lino Grezzo, bordo Orlo Caldo, link Inchiostro. No bg-white.
- **Height:** 64px (h-16), sempre fissa in cima (fixed).

### Feature Cards (Firma)

Il componente `f-card` con spotlight effect è il pattern distinto della landing.

- **Spotlight:** `radial-gradient(500px circle at mouse, rgba(196,113,74,0.09), transparent)` — un riflesso caldo che segue il cursore. Appare solo al hover, gestito via JS (`mousemove`).
- **Structure:** icona in Terra Chiara/Terra Bruciata + titolo Title + body Fine. No nested containers.
- **Hover state:** `-translate-y-0.5 shadow-md` — leggerissimo sollevamento, non animazione pesante.

## 6. Do's and Don'ts

### Do:
- **Do** usare Terra Bruciata come accento singolo e controllato: su bottoni primari, icone, link attivi, step marker. Ogni schermata deve poter mostrare la percentuale occupata dal colore — se supera 15%, è troppo.
- **Do** alternare Lino Grezzo e Lino Scuro tra le sezioni. Non usare sfondi bianchi puri o grigi freddi in nessuna superficie marketing.
- **Do** riservare Cormorant Garamond agli H1 e H2 delle superfici brand (landing, marketing, legal). Nell'app operativa (admin, form, dashboard) usa solo Instrument Sans.
- **Do** verificare il contrasto del testo: body text su Lino Grezzo deve essere Inchiostro (≥7:1) o Cenere Calda (≥5.1:1). Mai grigio generico gray-400/gray-300.
- **Do** usare `text-wrap: balance` su H1–H3 e `text-wrap: pretty` sui paragrafi lunghi. Obbligatorio.
- **Do** aggiungere `rgba(28, 20, 16, X)` come colore ombra quando un'ombra serve — non rgba(0,0,0,X).
- **Do** usare bordi Orlo Caldo (`#E5D8CF`) su tutti i container, input e divisori. Nessun gray-100 o gray-200.
- **Do** rispettare `prefers-reduced-motion`: ogni animazione deve avere la variante `@media (prefers-reduced-motion: reduce)` che la disattiva o la sostituisce con un crossfade istantaneo.

### Don't:
- **Don't** usare gradienti animati (gradPulse, hero gradients). Sono stati rimossi deliberatamente. Non reintrodurli.
- **Don't** usare blob decorativi (`.blob`, `blobMove`). Sono stati rimossi. Non reintrodurli.
- **Don't** usare float animations (`.float-card`, `cardFloat`). Rimossi. Non reintrodurli.
- **Don't** aggiungere eyebrow labels (piccolo testo uppercase con letter-spacing) sopra ogni sezione. Se usi un eyebrow, deve essere una scelta deliberata per un punto specifico, non il template di default.
- **Don't** mettere card in griglie uniformi con la stessa altezza e struttura ripetuta identica. Varia la struttura o scegli un layout diverso.
- **Don't** usare gradient-text (`background-clip: text` + gradient). Testo solido unico colore, sempre.
- **Don't** usare sfumature teal, blu-indigo, slate-900, gray come colori principali. Il refactoring teal→terra è stato intenzionale e permanente.
- **Don't** imitare l'estetica di Fresha o Treatwell (consumer marketplace, colori saturi-vivaci, immagini lifestyle pop).
- **Don't** imitare la densità enterprise di Salesforce o HubSpot (UI sovraccarica, ogni pixel occupato, layout tabellare ovunque).
- **Don't** costruire superfici luxury-fashion che sembrano incompatibili con un'app operativa: fotografia editoriale come sfondo, tipografia grande che occupa tutto il viewport, zero information density.
- **Don't** costruire qualcosa che sembra "generato dall'AI". Il test: se qualcuno può dire "AI made that" con sicurezza, è fallito. La palette calda, il serif, i nomi italiani esistono esattamente per evitare questo.
