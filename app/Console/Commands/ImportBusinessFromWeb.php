<?php

namespace App\Console\Commands;

use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ImportBusinessFromWeb extends Command
{
    protected $signature = 'business:import
                            {url? : URL homepage del salone (opzionale se si usa --from-json)}
                            {--business-id= : ID del business esistente da aggiornare}
                            {--dry-run : Mostra i dati estratti senza creare nulla}
                            {--force : Salta conferma interattiva}
                            {--no-media : Non scarica immagini (logo, gallery, avatar)}
                            {--save-json= : Salva il JSON estratto su file per revisione}
                            {--from-json= : Importa da un JSON già salvato, salta scraping e AI}';

    protected $description = 'Importa dati di un salone dal suo sito web usando AI';

    private const RELEVANT_KEYWORDS = [
        'servizi', 'services', 'prezzi', 'prices', 'listino',
        'staff', 'team', 'chi-siamo', 'about', 'il-team', 'i-nostri',
        'contatti', 'contact', 'orari', 'orario', 'dove-siamo', 'apertura',
        'barbiere', 'barber', 'parrucchiere', 'acconciature', 'galleria',
    ];

    private const BLACKLISTED_KEYWORDS = [
        'privacy', 'cookie', 'termini', 'carrello', 'checkout',
        'blog', '/tag/', '/categoria/', '/category/', 'login',
        'area-clienti', 'sitemap',
    ];

    private const DAY_MAP = [
        'monday'    => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday'  => 4, 'friday'  => 5, 'saturday'  => 6, 'sunday' => 0,
    ];

    private const DAY_SHORT = [
        'monday' => 'mon', 'tuesday' => 'tue', 'wednesday' => 'wed',
        'thursday' => 'thu', 'friday' => 'fri', 'saturday' => 'sat', 'sunday' => 'sun',
    ];

    public function handle(): int
    {
        $url        = $this->argument('url') ? rtrim($this->argument('url'), '/') : null;
        $businessId = $this->option('business-id') ? (int) $this->option('business-id') : null;
        $fromJson   = $this->option('from-json');

        if (! $url && ! $fromJson) {
            $this->error("Specificare un URL oppure usare --from-json=file.");
            return 1;
        }

        if ($businessId && ! Business::find($businessId)) {
            $this->error("Business ID {$businessId} non trovato.");
            return 1;
        }

        // ── Carica dati ───────────────────────────────────────────────────────
        if ($fromJson) {
            if (! file_exists($fromJson)) {
                $this->error("File non trovato: {$fromJson}");
                return 1;
            }
            $data = json_decode(file_get_contents($fromJson), true);
            if (! $data) {
                $this->error("JSON non valido in: {$fromJson}");
                return 1;
            }
            $this->line("Dati caricati da: {$fromJson}");
        } else {
            $this->info("Scraping: {$url}");
            $scraped = $this->scrapeWebsite($url);
            if (! $scraped) {
                $this->error('Impossibile recuperare il contenuto del sito.');
                return 1;
            }

            $this->info('Estrazione dati con AI...');
            $data = $this->extractWithAI($scraped, $url);
            if (! $data) {
                return 1;
            }

            // JSON-LD overrides AI per dati strutturati (più affidabili)
            $jsonLd = $scraped['jsonLd'];
            foreach (['name', 'phone', 'address', 'instagram_url', 'facebook_url', 'tiktok_url'] as $f) {
                if (! empty($jsonLd[$f])) {
                    $data['business'][$f] = $jsonLd[$f];
                }
            }
            if (! empty($jsonLd['logo_url'])) {
                $data['business']['logo_url'] = $jsonLd['logo_url'];
            }

            // HTML-extracted: social, logo, maps (ancora più affidabili del testo AI)
            foreach (['instagram_url', 'facebook_url', 'tiktok_url'] as $key) {
                if (! empty($scraped['socials'][$key])) {
                    $data['business'][$key] = $scraped['socials'][$key];
                }
            }
            if (! empty($scraped['logo'])) {
                $data['business']['logo_url'] = $scraped['logo'];
            }
            if (! empty($scraped['mapsEmbed'])) {
                $data['business']['google_maps_embed'] = $scraped['mapsEmbed'];
            } elseif (empty($data['business']['google_maps_embed']) && ! empty($data['business']['address'])) {
                $data['business']['google_maps_embed'] =
                    'https://maps.google.com/maps?q=' . urlencode($data['business']['address']) . '&output=embed';
            }
        }

        // ── Validazione ───────────────────────────────────────────────────────
        if (! $this->validateData($data) && ! $this->confirm('Continuare con dati parziali?', false)) {
            return 1;
        }

        // ── Salva JSON ────────────────────────────────────────────────────────
        if ($savePath = $this->option('save-json')) {
            $dir = dirname($savePath);
            if ($dir && ! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($savePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line("JSON salvato in: {$savePath}");
        }

        // ── Preview ───────────────────────────────────────────────────────────
        $this->displayPreview($data);

        if ($this->option('dry-run')) {
            $this->line("\n[dry-run] Nessun record creato.");
            return 0;
        }

        if (! $this->option('force') && ! $this->confirm("\nProcedere con l'importazione?", false)) {
            return 0;
        }

        // ── Import DB + media ─────────────────────────────────────────────────
        ['business_id' => $importedBusinessId, 'media' => $mediaQueue, 'avatars' => $avatarQueue, 'passwords' => $newPasswords]
            = $this->importData($data, $businessId);

        Artisan::call('page-builder:init', ['--business' => $importedBusinessId]);
        $this->line('  ✓ Page builder inizializzato');

        $galleryUrls = $this->applyAiDataToBlocks($data, $importedBusinessId);

        if (! $this->option('no-media')) {
            $this->downloadMedia($mediaQueue);
            $this->downloadAvatars($avatarQueue);
            if (! empty($galleryUrls)) {
                $this->downloadGalleryImages($galleryUrls, $importedBusinessId);
            }
        }

        if (! empty($newPasswords)) {
            $this->newLine();
            $this->info('╔══════════════════════════════════════════════════╗');
            $this->info('  CREDENZIALI STAFF (salva ora, non rivisualizzabili)');
            $this->info('╚══════════════════════════════════════════════════╝');
            $this->table(
                ['Email', 'Password temporanea'],
                array_map(fn ($email, $pass) => [$email, $pass], array_keys($newPasswords), $newPasswords)
            );
        }

        return 0;
    }

    // ─── Scraping ────────────────────────────────────────────────────────────

    private function scrapeWebsite(string $baseUrl): ?array
    {
        $allContent = '';
        $socials    = ['instagram_url' => null, 'facebook_url' => null, 'tiktok_url' => null];
        $images     = [];
        $logo       = null;
        $mapsEmbed  = null;
        $jsonLd     = [];
        $fetched    = [];

        try {
            $html = $this->fetch($baseUrl);
            if (! $html) {
                return null;
            }

            $fetched[$baseUrl] = true;
            $socials   = $this->extractSocialLinks($html);
            $logo      = $this->extractLogoUrl($html, $baseUrl);
            $mapsEmbed = $this->extractGoogleMapsEmbed($html);
            $jsonLd    = $this->extractJsonLd($html, $baseUrl);
            $images    = array_merge($images, $this->extractImageUrls($html, $baseUrl));
            $allContent .= $this->extractText($html) . "\n\n";

            $relevantLinks = $this->findRelevantLinks($html, $baseUrl);
            $sitemapLinks  = $this->fetchSitemapLinks($baseUrl);
            $allLinks      = array_values(array_unique(array_merge($relevantLinks, $sitemapLinks)));

            $this->line('  ' . count($allLinks) . ' pagine rilevanti trovate');

            foreach (array_slice($allLinks, 0, 5) as $link) {
                if (isset($fetched[$link])) {
                    continue;
                }
                $this->line("  → {$link}");
                $pageHtml = $this->fetch($link);
                if ($pageHtml) {
                    foreach (['instagram_url', 'facebook_url', 'tiktok_url'] as $key) {
                        if (empty($socials[$key])) {
                            $ps            = $this->extractSocialLinks($pageHtml);
                            $socials[$key] = $ps[$key] ?? null;
                        }
                    }
                    if (! $mapsEmbed) {
                        $mapsEmbed = $this->extractGoogleMapsEmbed($pageHtml);
                    }
                    if (empty($jsonLd)) {
                        $jsonLd = $this->extractJsonLd($pageHtml, $link);
                    }
                    $images = array_merge($images, $this->extractImageUrls($pageHtml, $link));
                    $allContent .= "--- {$link} ---\n" . $this->extractText($pageHtml) . "\n\n";
                }
                $fetched[$link] = true;
            }
        } catch (\Exception $e) {
            $this->error('Errore fetch: ' . $e->getMessage());
            return null;
        }

        return [
            'text'      => substr($allContent, 0, 15000),
            'socials'   => $socials,
            'images'    => array_values(array_unique(array_slice($images, 0, 40))),
            'logo'      => $logo,
            'mapsEmbed' => $mapsEmbed,
            'jsonLd'    => $jsonLd,
        ];
    }

    private function fetch(string $url): ?string
    {
        try {
            $resp = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SalonBot/1.0)'])
                ->get($url);
            return $resp->ok() ? $resp->body() : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function extractText(string $html): string
    {
        // footer e header intentionally kept: contengono orari, contatti, indirizzo
        $html = preg_replace('/<(script|style|nav|iframe)[^>]*>.*?<\/\1>/si', '', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/(\n\s*){3,}/', "\n\n", $text);
        return trim($text);
    }

    private function extractSocialLinks(string $html): array
    {
        $result = ['instagram_url' => null, 'facebook_url' => null, 'tiktok_url' => null];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        foreach ($matches[1] as $href) {
            if ($result['instagram_url'] === null && str_contains($href, 'instagram.com')) {
                $result['instagram_url'] = $href;
            }
            if ($result['facebook_url'] === null && str_contains($href, 'facebook.com')) {
                $result['facebook_url'] = $href;
            }
            if ($result['tiktok_url'] === null && str_contains($href, 'tiktok.com')) {
                $result['tiktok_url'] = $href;
            }
        }
        return $result;
    }

    /** @return list<string> Ogni elemento: "URL" oppure "URL [alt: testo]" */
    private function extractImageUrls(string $html, string $pageUrl): array
    {
        $parsed = parse_url($pageUrl);
        $base   = $parsed['scheme'] . '://' . $parsed['host'];

        preg_match_all('/<img[^>]+>/i', $html, $imgTags);

        $results = [];
        foreach ($imgTags[0] as $tag) {
            $src = null;
            foreach (['data-lazy-src', 'data-src', 'src'] as $attr) {
                if (preg_match('/' . $attr . '=["\']([^"\']+)["\']/', $tag, $m)) {
                    $src = $m[1];
                    break;
                }
            }
            if (! $src || str_starts_with($src, 'data:')) {
                continue;
            }
            if (preg_match('/\.(svg|gif|ico)/i', $src)) {
                continue;
            }
            if (preg_match('/(spinner|placeholder|pixel|1x1|loading)/i', $src)) {
                continue;
            }

            $alt = '';
            if (preg_match('/\balt=["\']([^"\']*)["\']/', $tag, $m) && $m[1] !== '') {
                $alt = $m[1];
            }

            $absolute  = str_starts_with($src, 'http') ? $src : $base . $src;
            $results[] = $alt ? "{$absolute} [alt: {$alt}]" : $absolute;
        }

        return $results;
    }

    private function extractLogoUrl(string $html, string $pageUrl): ?string
    {
        $parsed     = parse_url($pageUrl);
        $base       = $parsed['scheme'] . '://' . $parsed['host'];
        $toAbsolute = fn (string $src): string => str_starts_with($src, 'http') ? $src : $base . $src;

        $patterns = [
            '/<a[^>]+class=["\'][^"\']*custom-logo-link[^"\']*["\'][^>]*>\s*<img[^>]+(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i',
            '/<img[^>]+class=["\'][^"\']*\blogo\b[^"\']*["\'][^>]*(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i',
            '/<img[^>]+(?:src|data-src)=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*\blogo\b[^"\']*["\'][^>]*>/i',
            '/<img[^>]+id=["\'][^"\']*logo[^"\']*["\'][^>]*(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i',
            '/<img[^>]+alt=["\'][^"\']*logo[^"\']*["\'][^>]*(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i',
            '/<img[^>]+(?:src|data-src)=["\']([^"\']+)["\'][^>]*alt=["\'][^"\']*logo[^"\']*["\'][^>]*>/i',
            '/class=["\'][^"\']*\b(?:logo|brand|site-logo|navbar-brand)\b[^"\']*["\'][^>]*>(?:[^<]*<[^\/][^>]*>)*[^<]*<img[^>]+(?:src|data-src)=["\']([^"\']+)["\'][^>]*>/i',
            '/<header[^>]*>(?:(?!<\/header>).)*?<img[^>]+(?:src|data-src)=["\']([^"\']+(?:\.png|\.jpg|\.jpeg|\.webp|\.svg))["\'][^>]*>/si',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $src = end($m);
                if ($src && ! preg_match('/\.(gif|ico)$/i', $src)) {
                    return $toAbsolute($src);
                }
            }
        }

        return null;
    }

    private function extractGoogleMapsEmbed(string $html): ?string
    {
        if (preg_match('/<iframe[^>]+src=["\']([^"\']*google\.com\/maps[^"\']*)["\'][^>]*>/i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return null;
    }

    private function extractJsonLd(string $html, string $pageUrl): array
    {
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $blocks);

        $parsed     = parse_url($pageUrl);
        $base       = $parsed['scheme'] . '://' . $parsed['host'];
        $localTypes = [
            'LocalBusiness', 'HairSalon', 'BeautySalon', 'Barbershop',
            'HealthAndBeautyBusiness', 'Organization', 'Store',
        ];

        foreach ($blocks[1] as $raw) {
            $decoded = json_decode(trim($raw), true);
            if (! $decoded) {
                continue;
            }

            $items = isset($decoded['@graph']) ? $decoded['@graph'] : [$decoded];

            foreach ($items as $item) {
                if (! in_array($item['@type'] ?? '', $localTypes, true)) {
                    continue;
                }

                $result = [];

                if (! empty($item['name']))        $result['name']        = $item['name'];
                if (! empty($item['telephone']))   $result['phone']       = $item['telephone'];
                if (! empty($item['email']))       $result['email']       = $item['email'];
                if (! empty($item['description'])) $result['description'] = $item['description'];

                if (! empty($item['address'])) {
                    $addr = $item['address'];
                    if (is_array($addr)) {
                        $parts = array_filter([
                            $addr['streetAddress']   ?? null,
                            $addr['postalCode']      ?? null,
                            $addr['addressLocality'] ?? null,
                        ]);
                        $result['address'] = implode(', ', $parts);
                    } else {
                        $result['address'] = $addr;
                    }
                }

                if (! empty($item['openingHoursSpecification'])) {
                    $result['jsonld_hours'] = $item['openingHoursSpecification'];
                } elseif (! empty($item['openingHours'])) {
                    $result['jsonld_hours_text'] = $item['openingHours'];
                }

                if (! empty($item['logo'])) {
                    $logo = $item['logo'];
                    $src  = is_array($logo) ? ($logo['url'] ?? $logo['contentUrl'] ?? null) : $logo;
                    if ($src) {
                        $result['logo_url'] = str_starts_with($src, 'http') ? $src : $base . $src;
                    }
                }

                if (! empty($item['image'])) {
                    $imgs               = is_array($item['image']) ? $item['image'] : [$item['image']];
                    $result['jsonld_images'] = array_map(
                        fn ($i) => is_array($i) ? ($i['url'] ?? '') : $i,
                        $imgs
                    );
                }

                if (! empty($item['sameAs'])) {
                    $sameAs = is_array($item['sameAs']) ? $item['sameAs'] : [$item['sameAs']];
                    foreach ($sameAs as $link) {
                        if (str_contains($link, 'instagram.com')) $result['instagram_url'] = $link;
                        if (str_contains($link, 'facebook.com'))  $result['facebook_url']  = $link;
                        if (str_contains($link, 'tiktok.com'))    $result['tiktok_url']    = $link;
                    }
                }

                if (! empty($result)) {
                    return $result;
                }
            }
        }

        return [];
    }

    private function fetchSitemapLinks(string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $base   = $parsed['scheme'] . '://' . $parsed['host'];

        foreach (["$base/sitemap.xml", "$base/wp-sitemap.xml", "$base/sitemap_index.xml"] as $candidate) {
            $xml = $this->fetch($candidate);
            if (! $xml) {
                continue;
            }

            preg_match_all('/<loc>(.*?)<\/loc>/si', $xml, $matches);
            $links = [];
            foreach ($matches[1] as $loc) {
                $loc  = trim(html_entity_decode($loc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $path = strtolower(parse_url($loc, PHP_URL_PATH) ?? '');

                if (! str_starts_with($loc, $base)) {
                    continue;
                }
                foreach (self::BLACKLISTED_KEYWORDS as $bk) {
                    if (str_contains($path, $bk)) {
                        continue 2;
                    }
                }
                foreach (self::RELEVANT_KEYWORDS as $kw) {
                    if (str_contains($path, $kw)) {
                        $links[] = $loc;
                        break;
                    }
                }
            }

            if (! empty($links)) {
                $this->line('  Sitemap: ' . count($links) . ' URL rilevanti trovati');
                return $links;
            }
        }

        return [];
    }

    private function findRelevantLinks(string $html, string $baseUrl): array
    {
        $parsed = parse_url($baseUrl);
        $base   = $parsed['scheme'] . '://' . $parsed['host'];

        // Cattura href + anchor text per valutare anche il testo del link
        preg_match_all('/<a\s[^>]*href=["\']([^"\'#?][^"\']*)["\'][^>]*>(.*?)<\/a>/si', $html, $matches);

        $links = [];
        foreach ($matches[1] as $i => $href) {
            if (str_starts_with($href, 'http')) {
                $absolute = $href;
            } elseif (str_starts_with($href, '/')) {
                $absolute = $base . $href;
            } else {
                continue;
            }

            if (! str_starts_with($absolute, $base)) {
                continue;
            }

            $path       = strtolower(parse_url($absolute, PHP_URL_PATH) ?? '');
            $anchorText = strtolower(strip_tags($matches[2][$i] ?? ''));

            foreach (self::BLACKLISTED_KEYWORDS as $bk) {
                if (str_contains($path, $bk)) {
                    continue 2;
                }
            }

            foreach (self::RELEVANT_KEYWORDS as $kw) {
                if (str_contains($path, $kw) || str_contains($anchorText, $kw)) {
                    $links[] = $absolute;
                    break;
                }
            }
        }

        return array_values(array_unique($links));
    }

    // ─── AI Extraction ───────────────────────────────────────────────────────

    private function extractWithAI(array $scraped, string $url): ?array
    {
        $apiKey = env('ANTHROPIC_API_KEY');
        if (! $apiKey) {
            $this->error('ANTHROPIC_API_KEY non trovata in .env');
            return null;
        }

        $content   = $scraped['text'];
        $imageList = ! empty($scraped['images'])
            ? "\n\nIMAGINI TROVATE:\n" . implode("\n", $scraped['images'])
            : '';
        $jsonLdSection = ! empty($scraped['jsonLd'])
            ? "\n\nDATI JSON-LD (alta affidabilità — usa come base):\n"
              . json_encode($scraped['jsonLd'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '';

        $prompt = <<<PROMPT
Analizza il testo estratto da questo sito (URL: {$url}) e restituisci SOLO un JSON valido, senza markdown né backtick.{$jsonLdSection}

TESTO:
{$content}{$imageList}

Struttura JSON:
{
  "business": {
    "name": "Nome salone",
    "tagline": "Slogan (null se assente)",
    "description": "2-3 frasi descrittive",
    "address": "Indirizzo completo con città",
    "phone": "+39... (null se assente)",
    "whatsapp_number": "Numero senza + (null se assente)"
  },
  "hours": [
    {"day": "monday", "open": "09:00", "close": "13:00", "open_2": "15:00", "close_2": "19:00"}
  ],
  "services": [
    {"name": "Nome", "description": "Breve", "duration_minutes": 30, "price": 15.00}
  ],
  "staff": [
    {"name": "Nome Cognome", "role": "admin", "bio": "2-4 frasi", "photo_url": null}
  ],
  "gallery_images": ["URL1", "URL2"]
}

Regole:
- hours.day: lunedì=monday … domenica=sunday. Giorno chiuso = escludi. Pausa pranzo = usa open_2/close_2.
- staff.role: "admin" per titolare, "staff" per tutti gli altri.
- staff.photo_url: URL dalla lista immagini che sembra ritratto di persona. null se nessuna corrisponde.
- gallery_images: foto del salone/interni/prodotti dalla lista immagini. Escludi loghi e icone. Max 10.
- services.price: decimale o null. services.duration_minutes: stima se assente.
- Nessuna email per lo staff — verrà generata automaticamente.
- Restituisci SOLO il JSON.
PROMPT;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'       => 'claude-haiku-4-5-20251001',
                    'max_tokens'  => 4096,
                    'temperature' => 0,
                    'messages'    => [['role' => 'user', 'content' => $prompt]],
                ]);

            if (! $response->ok()) {
                $this->error('API error ' . $response->status() . ': ' . $response->body());
                return null;
            }

            $text = $response->json('content.0.text', '');
            $text = trim(preg_replace('/^```(?:json)?\s*/m', '', preg_replace('/```\s*$/m', '', $text)));
            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('JSON non valido: ' . json_last_error_msg());
                $this->line('Risposta AI: ' . substr($text, 0, 300));
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->error('Chiamata API fallita: ' . $e->getMessage());
            return null;
        }
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    private function validateData(array $data): bool
    {
        $validator = Validator::make($data, [
            'business.name'               => ['required', 'string', 'max:255'],
            'business.description'        => ['nullable', 'string'],
            'business.address'            => ['nullable', 'string'],
            'business.phone'              => ['nullable', 'string'],
            'hours'                       => ['array'],
            'hours.*.day'                 => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'hours.*.open'                => ['required', 'date_format:H:i'],
            'hours.*.close'               => ['required', 'date_format:H:i'],
            'services'                    => ['array'],
            'services.*.name'             => ['required', 'string', 'max:255'],
            'services.*.duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'services.*.price'            => ['nullable', 'numeric', 'min:0'],
            'staff'                       => ['array'],
            'staff.*.name'                => ['required', 'string', 'max:255'],
            'staff.*.role'                => ['nullable', 'in:admin,staff'],
            'gallery_images'              => ['array'],
            'gallery_images.*'            => ['url'],
        ]);

        if ($validator->fails()) {
            $this->warn('Problemi nei dati estratti:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("  · {$error}");
            }
            return false;
        }

        return true;
    }

    // ─── Preview ─────────────────────────────────────────────────────────────

    private function displayPreview(array $data): void
    {
        $this->newLine();
        $this->line('══════════════════════════════════════════');
        $this->line('  DATI ESTRATTI');
        $this->line('══════════════════════════════════════════');

        $b = $data['business'] ?? [];
        $this->line("\nBusiness:");
        $this->table(['Campo', 'Valore'], [
            ['Nome',            $b['name'] ?? '—'],
            ['Sottotitolo hero', $b['tagline'] ?? '—'],
            ['Descrizione',     ! empty($b['description']) ? Str::limit(strip_tags($b['description']), 80) : '—'],
            ['Indirizzo',       $b['address'] ?? '—'],
            ['Telefono',        $b['phone'] ?? '—'],
            ['Instagram',       $b['instagram_url'] ?? '—'],
            ['Facebook',        $b['facebook_url'] ?? '—'],
            ['TikTok',          $b['tiktok_url'] ?? '—'],
            ['WhatsApp',        $b['whatsapp_number'] ?? '—'],
            ['Logo',            ! empty($b['logo_url']) ? '✓ ' . Str::limit($b['logo_url'], 60) : '—'],
            ['Google Maps',     ! empty($b['google_maps_embed']) ? '✓' : '—'],
            ['Foto galleria',   ! empty($data['gallery_images']) ? count($data['gallery_images']) . ' trovate (blocco galleria)' : '—'],
        ]);

        if (! empty($data['hours'])) {
            $this->line('Orari:');
            $this->table(['Giorno', 'Mattina', 'Pomeriggio'], array_map(fn ($h) => [
                $h['day'],
                ($h['open'] ?? '') . ' – ' . ($h['close'] ?? ''),
                ! empty($h['open_2']) ? ($h['open_2'] . ' – ' . ($h['close_2'] ?? '')) : '—',
            ], $data['hours']));
        }

        if (! empty($data['services'])) {
            $this->line('Servizi (' . count($data['services']) . '):');
            $this->table(['Nome', 'Durata', 'Prezzo'], array_map(fn ($s) => [
                $s['name'],
                ($s['duration_minutes'] ?? '?') . ' min',
                $s['price'] !== null ? '€' . number_format((float) $s['price'], 2) : '—',
            ], $data['services']));
        }

        if (! empty($data['staff'])) {
            $businessName = $data['business']['name'] ?? 'salone';
            $this->line('Staff (' . count($data['staff']) . '):');
            $this->table(['Nome', 'Ruolo', 'Email generata', 'Foto'], array_map(fn ($s) => [
                $s['name'],
                $s['role'] ?? 'staff',
                $this->generateEmail($s['name'], $businessName),
                ! empty($s['photo_url']) ? '✓' : '—',
            ], $data['staff']));
        }
    }

    // ─── Import (solo DB, nessun HTTP) ────────────────────────────────────────

    private function importData(array $data, ?int $businessId): array
    {
        $mediaQueue    = [];
        $avatarQueue   = [];
        $newPasswords  = []; // [email => password] per utenti appena creati
        $businessRef   = new Business();

        DB::transaction(function () use ($data, $businessId, &$mediaQueue, &$avatarQueue, &$newPasswords, &$businessRef) {
            // Business
            if ($businessId) {
                $business = Business::findOrFail($businessId);
                $this->line("Business esistente: {$business->name} (ID: {$business->id})");
            } else {
                $name      = $data['business']['name'] ?? 'Nuovo Salone';
                $subdomain = Str::slug($name);
                $base      = $subdomain;
                $n         = 2;
                while (Business::where('subdomain', $subdomain)->exists()) {
                    $subdomain = $base . '-' . $n++;
                }
                $business = Business::create(['name' => $name, 'subdomain' => $subdomain]);
                $this->info("Business creato: {$business->name} (subdomain: {$subdomain}, ID: {$business->id})");
            }

            $businessRef = $business;

            // SystemSetting — crea se assente
            SystemSetting::firstOrCreate(
                ['business_id' => $business->id],
                [
                    'slot_generation_weeks'       => 4,
                    'slot_granularity_minutes'    => 15,
                    'timezone'                    => 'Europe/Rome',
                    'booking_max_days_ahead'      => 60,
                    'cancellation_deadline_hours' => 24,
                    'reminder_count'              => 1,
                    'reminder_1_hours'            => 24,
                    'payment_mode'                => 'both',
                    'reviews_enabled'             => true,
                    'review_request_enabled'      => false,
                    'loyalty_enabled'             => false,
                    'loyalty_points_per_euro'     => 1,
                    'loyalty_reward_threshold'    => 100,
                    'loyalty_reward_percentage'   => 10,
                    'loyalty_tiers'               => null,
                    'follow_up_reminders_enabled' => false,
                    'follow_up_reminder_days'     => 30,
                ]
            );

            // SalonProfile — senza tagline/description (ora nei page blocks)
            $b       = $data['business'] ?? [];
            $profile = SalonProfile::updateOrCreate(
                ['business_id' => $business->id],
                array_filter([
                    'name'              => $b['name'] ?? null,
                    'address'           => $b['address'] ?? null,
                    'phone'             => $b['phone'] ?? null,
                    'instagram_url'     => $b['instagram_url'] ?? null,
                    'facebook_url'      => $b['facebook_url'] ?? null,
                    'tiktok_url'        => $b['tiktok_url'] ?? null,
                    'whatsapp_number'   => $b['whatsapp_number'] ?? null,
                    'google_maps_embed' => $b['google_maps_embed'] ?? null,
                    'opening_hours'     => $this->buildOpeningHours($data['hours'] ?? []),
                ], fn ($v) => $v !== null)
            );

            if (! empty($b['logo_url'])) {
                $profile->clearMediaCollection('logo');
                $profile->clearMediaCollection('favicon');
                $mediaQueue[] = ['model' => $profile, 'url' => $b['logo_url'], 'collection' => 'logo'];
                $mediaQueue[] = ['model' => $profile, 'url' => $b['logo_url'], 'collection' => 'favicon'];
            }

            $this->line('  ✓ SalonProfile aggiornato');

            // Roles
            Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
            Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

            // Staff
            $staffIds = [];
            foreach ($data['staff'] ?? [] as $idx => $s) {
                $email    = $this->generateEmail($s['name'], $data['business']['name'] ?? 'salone');
                $tempPass = Str::random(12);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'                 => $s['name'],
                        'password'             => bcrypt($tempPass),
                        'business_id'          => $business->id,
                        'bio'                  => $s['bio'] ?? null,
                        'sort_order'           => $idx + 1,
                        'must_change_password' => true,
                    ]
                );

                if (! $user->wasRecentlyCreated) {
                    $user->update([
                        'business_id' => $business->id,
                        'bio'         => $s['bio'] ?? $user->bio,
                        'sort_order'  => $user->sort_order ?: $idx + 1,
                    ]);
                    $tempPass = null;
                }

                $role = ($s['role'] ?? 'staff') === 'admin' ? ['admin', 'staff'] : ['staff'];
                $user->syncRoles($role);
                $user->businesses()->syncWithoutDetaching([$business->id]);
                $staffIds[$idx] = $user->id;

                if ($tempPass) {
                    $newPasswords[$email] = $tempPass;
                }
                $this->line('  ✓ Staff: ' . $user->name . ' <' . $email . '> (' . implode('+', $role) . ')' . ($tempPass ? '' : ' — già esistente'));

                if (! empty($s['photo_url'])) {
                    $avatarQueue[] = ['user' => $user, 'url' => $s['photo_url']];
                }
            }

            // Services
            foreach ($data['services'] ?? [] as $idx => $s) {
                $service = Service::updateOrCreate(
                    ['business_id' => $business->id, 'name' => $s['name']],
                    [
                        'description'      => $s['description'] ?? null,
                        'duration_minutes' => (int) ($s['duration_minutes'] ?? 30),
                        'price'            => $s['price'] ?? 0,
                        'active'           => true,
                        'sort_order'       => $idx + 1,
                    ]
                );
                $service->staff()->sync(array_values($staffIds));
                $this->line('  ✓ Servizio: ' . $service->name);
            }

            // Availability rules
            foreach ($staffIds as $staffId) {
                AvailabilityRule::where('user_id', $staffId)->delete();
                foreach ($data['hours'] ?? [] as $slot) {
                    $day = self::DAY_MAP[$slot['day']] ?? null;
                    if ($day === null) {
                        continue;
                    }
                    AvailabilityRule::create([
                        'business_id'  => $business->id,
                        'user_id'      => $staffId,
                        'day_of_week'  => $day,
                        'start_time'   => $slot['open'],
                        'end_time'     => $slot['close'],
                        'start_time_2' => $slot['open_2'] ?? null,
                        'end_time_2'   => $slot['close_2'] ?? null,
                        'is_available' => true,
                    ]);
                }
            }
            $this->line('  ✓ Orari di disponibilità impostati');

            $this->newLine();
            $this->info("Import DB completato. Business ID: {$business->id}");
        });

        return [
            'business_id' => $businessRef->id,
            'media'       => $mediaQueue,
            'avatars'     => $avatarQueue,
            'passwords'   => $newPasswords,
        ];
    }

    /** @return list<string> Gallery image URLs to download after the transaction */
    private function applyAiDataToBlocks(array $data, int $businessId): array
    {
        $b = $data['business'] ?? [];

        $update = function (string $type, array $fields) use ($businessId): void {
            $block = BusinessPageBlock::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('block_type', $type)
                ->first();
            if (! $block) return;
            if (isset($fields['content'])) {
                $fields['content'] = array_merge($block->content ?? [], array_filter($fields['content'], fn ($v) => $v !== null && $v !== ''));
            }
            $block->update($fields);
        };

        // Hero — variante classica (immagine sfondo piena), titolo + sottotitolo
        $update('hero', [
            'is_enabled' => true,
            'variant'    => 'classic',
            'content' => [
                'title'    => $b['name'] ?? null,
                'subtitle' => $b['tagline'] ?? null,
            ],
        ]);

        // About — descrizione in HTML
        $aboutContent = [];
        if (! empty($b['description'])) {
            $aboutContent['body'] = '<p>' . implode('</p><p>', array_map(
                fn ($p) => e(trim($p)),
                preg_split('/\n{2,}/', trim($b['description']))
            )) . '</p>';
        }
        $update('about', ['is_enabled' => true, 'content' => $aboutContent]);

        // Services — lista compatta con bottone prenotazione
        $update('services', ['is_enabled' => true, 'variant' => 'compact_list']);
        $update('staff',    ['is_enabled' => true]);

        // Contact info — usa SalonProfile, basta abilitarlo
        $update('contact_info', ['is_enabled' => true]);

        // Reviews — abilita (nessun contenuto specifico dall'import)
        $update('reviews', ['is_enabled' => true]);

        // CTA
        $ctaContent = [
            'title'        => 'Prenota il tuo appuntamento',
            'button_label' => 'Prenota ora',
        ];
        if (! empty($b['tagline'])) {
            $ctaContent['subtitle'] = $b['tagline'];
        }
        $update('cta', ['is_enabled' => true, 'content' => $ctaContent]);

        // Map — disabilitata di default all'import
        $update('map', ['is_enabled' => false]);

        // Gallery — ritorna le URL per il download separato
        $galleryUrls = array_slice($data['gallery_images'] ?? [], 0, 12);
        if (! empty($galleryUrls)) {
            $update('gallery', ['is_enabled' => true]);
        }

        return $galleryUrls;
    }

    // ─── Media download (fuori transazione) ──────────────────────────────────

    private function downloadMedia(array $mediaQueue): void
    {
        if (empty($mediaQueue)) {
            return;
        }

        $this->info('Download media (' . count($mediaQueue) . ' file)...');
        foreach ($mediaQueue as $item) {
            try {
                $item['model']->addMediaFromUrl($item['url'])->toMediaCollection($item['collection']);
                $this->line('  ✓ ' . $item['collection'] . ': ' . Str::limit($item['url'], 60));
            } catch (\Exception $e) {
                $this->warn('  → Saltato: ' . Str::limit($item['url'], 50) . ' — ' . $e->getMessage());
            }
        }
    }

    private function downloadAvatars(array $avatarQueue): void
    {
        if (empty($avatarQueue)) {
            return;
        }

        $this->info('Download avatar staff (' . count($avatarQueue) . ')...');
        foreach ($avatarQueue as $item) {
            /** @var User $user */
            $user = $item['user'];
            $url  = $item['url'];
            try {
                $contents = Http::timeout(12)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SalonBot/1.0)'])
                    ->get($url)
                    ->body();
                $path = 'avatars/' . $user->id . '.jpg';
                Storage::disk('public')->put($path, $contents);
                $user->update(['avatar_path' => $path]);
                $this->line('  ✓ avatar: ' . $user->name);
            } catch (\Exception $e) {
                $this->warn('  → Avatar saltato (' . $user->name . '): ' . $e->getMessage());
            }
        }
    }

    private function downloadGalleryImages(array $urls, int $businessId): void
    {
        $this->info('Download immagini galleria (' . count($urls) . ')...');
        $paths = [];

        foreach ($urls as $i => $url) {
            $url = trim(preg_replace('/\s*\[alt:.*?\]$/', '', $url));
            if (empty($url)) continue;
            try {
                $contents = Http::timeout(12)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SalonBot/1.0)'])
                    ->get($url)
                    ->body();
                $webp = $this->toWebp($contents);
                if ($webp !== null) {
                    $path = "site-builder/gallery/{$businessId}/{$i}.webp";
                    Storage::disk('public')->put($path, $webp);
                } else {
                    $path = "site-builder/gallery/{$businessId}/{$i}.jpg";
                    Storage::disk('public')->put($path, $contents);
                }
                $paths[] = $path;
                $this->line("  ✓ galleria [{$i}]");
            } catch (\Exception $e) {
                $this->warn("  → Saltata [{$i}]: " . $e->getMessage());
            }
        }

        if (! empty($paths)) {
            $gallery = BusinessPageBlock::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('block_type', 'gallery')
                ->first();
            if ($gallery) {
                $gallery->update(['content' => array_merge($gallery->content ?? [], ['images' => $paths])]);
            }
            $this->line('  ✓ ' . count($paths) . ' immagini salvate nel blocco galleria');

            // Prima immagine galleria → sfondo hero classico
            $hero = BusinessPageBlock::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('block_type', 'hero')
                ->first();
            if ($hero) {
                $hero->update(['content' => array_merge($hero->content ?? [], ['image' => $paths[0]])]);
                $this->line('  ✓ Immagine hero impostata: ' . $paths[0]);
            }
        }
    }

    private function buildOpeningHours(array $hours): array
    {
        $result = [];
        foreach (array_keys(self::DAY_SHORT) as $day) {
            $result[self::DAY_SHORT[$day]] = ['type' => 'closed'];
        }
        foreach ($hours as $slot) {
            $short = self::DAY_SHORT[$slot['day']] ?? null;
            if (! $short) {
                continue;
            }
            if (! empty($slot['open_2']) && ! empty($slot['close_2'])) {
                $result[$short] = [
                    'type'            => 'split',
                    'morning_open'    => $slot['open'],
                    'morning_close'   => $slot['close'],
                    'afternoon_open'  => $slot['open_2'],
                    'afternoon_close' => $slot['close_2'],
                ];
            } else {
                $result[$short] = [
                    'type'       => 'continuous',
                    'open_time'  => $slot['open'],
                    'close_time' => $slot['close'],
                ];
            }
        }
        return $result;
    }

    private function generateEmail(string $staffName, string $businessName): string
    {
        return Str::slug($staffName) . '@' . Str::slug($businessName) . '.it';
    }

    private function toWebp(string $contents, int $quality = 82): ?string
    {
        $img = @imagecreatefromstring($contents);
        if (! $img) return null;
        ob_start();
        imagewebp($img, null, $quality);
        imagedestroy($img);
        return ob_get_clean() ?: null;
    }
}
