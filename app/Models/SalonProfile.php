<?php

namespace App\Models;

use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'business_id',
    'name', 'tagline', 'logo_path', 'theme', 'theme_mode', 'hero_image_preset',
    'font_pair', 'border_style', 'bg_texture',
    'phone', 'address',
    'description', 'cancellation_policy', 'google_maps_embed',
    'opening_hours',
    'instagram_url', 'facebook_url', 'tiktok_url', 'whatsapp_number',
    'email_greeting', 'email_footer_note', 'email_accent_color',
])]
class SalonProfile extends Model implements HasMedia
{
    use BelongsToBusiness, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
        ];
    }

    public static function current(): self
    {
        if (! app()->bound('current_business_id')) {
            return new self(['name' => config('app.name', 'Booking App')]);
        }

        return self::firstOrCreate(
            ['business_id' => Business::currentId()],
            [
                'name' => 'Il mio salone',
            ]
        );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile()->useDisk('public');
        $this->addMediaCollection('cover')->singleFile()->useDisk('public');
        $this->addMediaCollection('favicon')->singleFile()->useDisk('public');
        $this->addMediaCollection('gallery')->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(200)
            ->nonQueued();

        $this->addMediaConversion('web')
            ->width(1200)
            ->height(800)
            ->nonQueued();
    }

    public function logoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('logo');
        if ($url) {
            return $url;
        }

        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    public function heroImageUrl(): ?string
    {
        $custom = $this->getFirstMediaUrl('cover', 'web') ?: $this->getFirstMediaUrl('cover');
        if ($custom) {
            return $custom;
        }

        if ($this->hero_image_preset) {
            return static::heroPresets()[$this->hero_image_preset]['url'] ?? null;
        }

        return null;
    }

    public static function heroPresets(): array
    {
        $base = 'https://images.unsplash.com/';
        return [
            'salon1'     => ['label' => 'Salone classico',    'url' => $base . 'photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1560066984-138dadb4c035?auto=format&fit=crop&w=480&h=200&q=70'],
            'salon2'     => ['label' => 'Atelier moderno',    'url' => $base . 'photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1522337360788-8b13dee7a37e?auto=format&fit=crop&w=480&h=200&q=70'],
            'barber'     => ['label' => 'Barbershop',         'url' => $base . 'photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=480&h=200&q=70'],
            'spa'        => ['label' => 'Spa & benessere',    'url' => $base . 'photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=480&h=200&q=70'],
            'beauty'     => ['label' => 'Centro estetico',    'url' => $base . 'photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=480&h=200&q=70'],
            'hair'       => ['label' => 'Hair salon',         'url' => $base . 'photo-1562322140-8baeececf3df?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1562322140-8baeececf3df?auto=format&fit=crop&w=480&h=200&q=70'],
            'luxury'     => ['label' => 'Lusso & raffinatezza', 'url' => $base . 'photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1516975080664-ed2fc6a32937?auto=format&fit=crop&w=480&h=200&q=70'],
            'nail'       => ['label' => 'Nail salon',         'url' => $base . 'photo-1604654894610-df63bc536371?auto=format&fit=crop&w=1920&h=640&q=80', 'thumb' => $base . 'photo-1604654894610-df63bc536371?auto=format&fit=crop&w=480&h=200&q=70'],
        ];
    }

    public function faviconUrl(): ?string
    {
        return $this->getFirstMediaUrl('favicon') ?: null;
    }

    public function cancellationPolicyHtml(): string
    {
        $hours = \App\Models\SystemSetting::getCancellationDeadlineHours();

        $contacts = [];
        if ($this->phone) {
            $contacts[] = 'al telefono (<a href="tel:' . e($this->phone) . '">' . e($this->phone) . '</a>)';
        }
        if ($this->whatsapp_number) {
            $contacts[] = '<a href="https://wa.me/' . e($this->whatsapp_number) . '">WhatsApp</a>';
        }

        $contactStr = match (count($contacts)) {
            0       => 'contattandoci direttamente',
            1       => 'contattandoci direttamente ' . $contacts[0],
            default => 'contattandoci direttamente ' . $contacts[0] . ' o via ' . $contacts[1],
        };

        return '<p>Le prenotazioni possono essere cancellate fino a <strong>' . $hours . '&nbsp;ore</strong> prima dell\'appuntamento.'
            . ' Passata questa finestra, la cancellazione non è più possibile tramite il portale; per emergenze ' . $contactStr . '.</p>'
            . '<p style="margin-top:14px">Se hai pagato online al momento della prenotazione, il rimborso viene elaborato automaticamente'
            . ' sul metodo di pagamento originale non appena la cancellazione viene confermata.'
            . ' I tempi di accredito dipendono dalla tua banca (di solito 3–5 giorni lavorativi).'
            . ' Nessun rimborso è previsto per cancellazioni effettuate a meno di <strong>' . $hours . '&nbsp;ore</strong> dall\'appuntamento.</p>';
    }
}
