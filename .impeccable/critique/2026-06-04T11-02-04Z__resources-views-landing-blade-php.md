---
target: landing
total_score: 30
p0_count: 0
p1_count: 4
timestamp: 2026-06-04T11-02-04Z
slug: resources-views-landing-blade-php
---
## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Scroll progress bar presente; n/a per landing statica |
| 2 | Match System / Real World | 4 | Italiano naturale, terminologia da salone corretta, zero jargon |
| 3 | User Control and Freedom | 3 | Anchor nav funziona, FAQ accordion chiaro |
| 4 | Consistency and Standards | 3 | Palette post-redesign coerente; eyebrow ripetuto, teal-500 avatar residuo |
| 5 | Error Prevention | 3 | Scope limitato per landing statica; contact form ha validazione |
| 6 | Recognition Rather Than Recall | 4 | Tutto visibile: feature, prezzi, step, FAQ |
| 7 | Flexibility and Efficiency | 2 | Pagina statica, singolo percorso CTA, nessun shortcut |
| 8 | Aesthetic and Minimalist Design | 2 | Eyebrow su ogni sezione, card grid identiche, gradient text |
| 9 | Error Recovery | 3 | Nessuna interazione complessa; form con inline errors |
| 10 | Help and Documentation | 3 | FAQ solida, contatto in nav, privacy/terms in footer |
| Total | | 30/40 | Good |

## Anti-Patterns Verdict

LLM: Hero dark + serif funziona. Tre segnali persistono: font pairing su reflex-reject list, background cream AI-default, eyebrow su ogni sezione.

Detector (5 findings): gradient-text REAL (landing.scss:140), layout-transition REAL (landing.scss:109), em-dash-overuse VERIFY, numbered-section-markers FALSE POSITIVE (sequenza legittima), single-font FALSE POSITIVE (due font in uso).

## Priority Issues

[P1] Gradient text .text-grad nel hero — absolute ban confermato da detector (landing.scss:140)
[P1] Eyebrow label su 7 sezioni consecutive — absolute ban × 7
[P1] Feature section identical card grid × 6 — absolute ban
[P1] Zero imagery del prodotto — pagina interamente testuale
[P2] transition: width su #spb — layout thrash (landing.scss:109)

## Persona Red Flags

Jordan: percorso verso pricing richiede 8-10 scroll su mobile, link Prezzi assente nella mobile drawer.
Casey: CTA porta a pagina separata, nessun inline mini-form, alto abbandono mobile.
Titolare salone italiano: zero prove visive del prodotto reale, prova sociale generica senza naming.

## Minor Observations

- teal-500 residuo in PHP data array testimonial avatar
- text-slate-300/500 su bg-ink (cold neutrals su warm dark)
- text-wrap: balance mancante su H2 sezioni
- JS counter trust bar: animazione rapida (1.4s), potrebbe essere più dramatico
