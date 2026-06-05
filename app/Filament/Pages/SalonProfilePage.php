<?php

namespace App\Filament\Pages;

use App\Models\SalonProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle; // kept for possible future use
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Utilities\Get;

class SalonProfilePage extends Page
{
    protected string $view = 'filament.pages.salon-profile';

    protected static ?string $navigationLabel = 'Profilo Salone';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public function mount(): void
    {
        $profile = SalonProfile::current();
        $hours   = $profile->opening_hours ?? [];

        $formData = [
            'name'                 => $profile->name,
            'tagline'              => $profile->tagline,
            'theme'                => $profile->theme             ?? 'luxury',
            'theme_mode'           => $profile->theme_mode        ?? 'light',
            'hero_image_preset'    => $profile->hero_image_preset ?? '',
            'announcement_active'  => (bool) $profile->announcement_active,
            'announcement_text'    => $profile->announcement_text,
            'booking_button_label' => $profile->booking_button_label,
            'meta_description'     => $profile->meta_description,
            'owner_signature'      => $profile->owner_signature,
            'font_pair'            => $profile->font_pair  ?? 'classic',
            'border_style'         => $profile->border_style ?? 'sharp',

            'phone'                => $profile->phone,
            'address'              => $profile->address,

            'description'          => $profile->description,
            'google_maps_embed'    => $profile->google_maps_embed,
            'google_review_url'    => $profile->google_review_url,
            'instagram_url'        => $profile->instagram_url,
            'facebook_url'         => $profile->facebook_url,
            'tiktok_url'           => $profile->tiktok_url,
            'whatsapp_number'      => $profile->whatsapp_number,

            'email_greeting'     => $profile->email_greeting     ?? "Ciao {nome},\nhai un nuovo appuntamento confermato.",
            'email_footer_note'  => $profile->email_footer_note  ?? 'Puoi gestire l\'appuntamento dall\'area riservata.',
            'email_accent_color' => $profile->email_accent_color,
        ];

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $d    = $hours[$day] ?? [];
            // backward compat: old format had 'open' bool, new has 'type' enum
            $type = $d['type'] ?? ($d['open'] ?? false ? 'split' : 'closed');
            $formData["hours_{$day}_type"]            = $type;
            $formData["hours_{$day}_open_time"]       = $d['open_time']       ?? '09:00';
            $formData["hours_{$day}_close_time"]      = $d['close_time']      ?? '19:00';
            $formData["hours_{$day}_morning_open"]    = $d['morning_open']    ?? '09:00';
            $formData["hours_{$day}_morning_close"]   = $d['morning_close']   ?? '13:00';
            $formData["hours_{$day}_afternoon_open"]  = $d['afternoon_open']  ?? '15:00';
            $formData["hours_{$day}_afternoon_close"] = $d['afternoon_close'] ?? '19:30';
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $days = [
            'mon' => 'Lunedì',
            'tue' => 'Martedì',
            'wed' => 'Mercoledì',
            'thu' => 'Giovedì',
            'fri' => 'Venerdì',
            'sat' => 'Sabato',
            'sun' => 'Domenica',
        ];

        $hoursFields = [];
        foreach ($days as $key => $label) {
            $hoursFields[] = Grid::make(6)->schema([
                Select::make("hours_{$key}_type")
                    ->label($label)
                    ->options([
                        'closed'     => 'Chiuso',
                        'continuous' => 'Orario continuato',
                        'split'      => 'Spezzato (pausa pranzo)',
                    ])
                    ->default('closed')
                    ->live()
                    ->columnSpan(2),

                TextInput::make("hours_{$key}_open_time")
                    ->label('Apertura')
                    ->placeholder('09:00')
                    ->visible(fn(Get $get) => $get("hours_{$key}_type") === 'continuous')
                    ->columnSpan(2),

                TextInput::make("hours_{$key}_close_time")
                    ->label('Chiusura')
                    ->placeholder('19:00')
                    ->visible(fn(Get $get) => $get("hours_{$key}_type") === 'continuous')
                    ->columnSpan(2),

                TextInput::make("hours_{$key}_morning_open")
                    ->label('Mat. apertura')
                    ->placeholder('09:00')
                    ->visible(fn(Get $get) => $get("hours_{$key}_type") === 'split')
                    ->columnSpan(1),

                TextInput::make("hours_{$key}_morning_close")
                    ->label('Mat. chiusura')
                    ->placeholder('13:00')
                    ->visible(fn(Get $get) => $get("hours_{$key}_type") === 'split')
                    ->columnSpan(1),

                TextInput::make("hours_{$key}_afternoon_open")
                    ->label('Pom. apertura')
                    ->placeholder('15:00')
                    ->visible(fn(Get $get) => $get("hours_{$key}_type") === 'split')
                    ->columnSpan(1),

                TextInput::make("hours_{$key}_afternoon_close")
                    ->label('Pom. chiusura')
                    ->placeholder('19:30')
                    ->visible(fn(Get $get) => $get("hours_{$key}_type") === 'split')
                    ->columnSpan(1),
            ]);
        }

        return $schema
            ->statePath('data')
            ->model(SalonProfile::current()->load('media'))
            ->schema([
                Tabs::make()->tabs([

                    Tab::make('Identità')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nome del salone')
                                ->required(),
                            TextInput::make('tagline')
                                ->label('Tagline'),
                        ]),
                        TextInput::make('booking_button_label')
                            ->label('Testo del pulsante "Prenota"')
                            ->placeholder('Prenota un appuntamento')
                            ->helperText('Lascia vuoto per usare il testo predefinito.')
                            ->columnSpanFull(),
                        Toggle::make('announcement_active')
                            ->label('Mostra banner avvisi')
                            ->helperText('Una striscia in cima alla vetrina per ferie, promozioni o comunicazioni.')
                            ->live(),
                        TextInput::make('announcement_text')
                            ->label('Testo dell\'avviso')
                            ->placeholder('es. Chiusi per ferie dal 10 al 20 agosto')
                            ->maxLength(160)
                            ->visible(fn(Get $get) => (bool) $get('announcement_active'))
                            ->columnSpanFull(),
                        Radio::make('theme')
                            ->label('Famiglia di colori')
                            ->options([
                                'luxury'     => 'Luxury',
                                'rosa'       => 'Rosa',
                                'verde'      => 'Verde',
                                'notte'      => 'Notte',
                                'minimal'    => 'Minimal',
                                'viola'      => 'Viola',
                                'terracotta' => 'Terracotta',
                                'acqua'      => 'Acqua',
                                'cipria'     => 'Cipria',
                            ])
                            ->default('luxury')
                            ->view('filament.forms.theme-picker')
                            ->columnSpanFull(),
                        Radio::make('theme_mode')
                            ->label('Modalità predefinita')
                            ->options(['light' => 'Chiaro', 'dark' => 'Scuro'])
                            ->default('light')
                            ->inline()
                            ->helperText('Il cliente può cambiare la modalità con il toggle sul sito.')
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            SpatieMediaLibraryFileUpload::make('logo')
                                ->label('Logo')
                                ->collection('logo')
                                ->image()
                                ->maxSize(2048),
                            SpatieMediaLibraryFileUpload::make('cover')
                                ->label('Immagine hero (carica la tua)')
                                ->collection('cover')
                                ->image()
                                ->maxSize(5120)
                                ->helperText('Ha priorità sulle immagini predefinite.'),
                        ]),
                        Radio::make('hero_image_preset')
                            ->label('Oppure scegli un\'immagine predefinita')
                            ->options(
                                array_merge(
                                    ['' => 'Nessuna'],
                                    array_map(fn($p) => $p['label'], \App\Models\SalonProfile::heroPresets())
                                )
                            )
                            ->dehydrateStateUsing(fn($state) => $state ?: null)
                            ->view('filament.forms.hero-preset-picker')
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            SpatieMediaLibraryFileUpload::make('favicon')
                                ->label('Favicon')
                                ->collection('favicon')
                                ->image()
                                ->maxSize(512)
                                ->helperText('Immagine quadrata (PNG o ICO). Consigliato: 64×64px o 192×192px.'),
                        ]),
                    ]),

                    Tab::make('Stile')->schema([
                        Radio::make('font_pair')
                            ->label('Coppia di font')
                            ->options([
                                'classic' => 'Classic Luxury',
                                'modern'  => 'Modern Clean',
                                'elegant' => 'Elegant Serif',
                                'minimal' => 'Minimal Sans',
                            ])
                            ->default('classic')
                            ->view('filament.forms.font-picker')
                            ->columnSpanFull(),

                        Radio::make('border_style')
                            ->label('Stile bordi e pulsanti')
                            ->options([
                                'sharp'   => 'Sharp',
                                'rounded' => 'Rounded',
                                'pill'    => 'Pill',
                            ])
                            ->default('sharp')
                            ->view('filament.forms.border-picker')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Descrizione')->schema([
                        RichEditor::make('description')
                            ->label('Chi siamo')
                            ->columnSpanFull(),
                        TextInput::make('owner_signature')
                            ->label('Firma del titolare')
                            ->placeholder('es. Con cura, Giulia e il team')
                            ->helperText('Una firma breve mostrata in fondo alla sezione "Il salone".')
                            ->maxLength(120)
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Galleria')->schema([
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('Foto galleria')
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Orari')->schema($hoursFields),

                    Tab::make('Contatti & Social')->schema([
                        TextInput::make('phone')->label('Telefono'),
                        TextInput::make('address')
                            ->label('Indirizzo')
                            ->columnSpanFull(),
                        Textarea::make('google_maps_embed')
                            ->label('Google Maps embed URL')
                            ->placeholder('https://www.google.com/maps/embed?...')
                            ->helperText('Incolla solo il valore src dell\'iframe di Google Maps')
                            ->rows(2)
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            TextInput::make('instagram_url')->label('Instagram')->url(),
                            TextInput::make('facebook_url')->label('Facebook')->url(),
                            TextInput::make('tiktok_url')->label('TikTok')->url(),
                            TextInput::make('whatsapp_number')
                                ->label('WhatsApp')
                                ->placeholder('39xxxxxxxxxx')
                                ->helperText('Numero internazionale senza + (es. 39333000000)'),
                        ]),
                        TextInput::make('google_review_url')
                            ->label('Link recensione Google')
                            ->url()
                            ->placeholder('https://g.page/r/...')
                            ->helperText('Aggiunge un pulsante "Lascia una recensione" nella sezione recensioni della vetrina.')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Email')->schema([
                        Textarea::make('email_greeting')
                            ->label('Messaggio di benvenuto')
                            ->placeholder('es. Grazie per aver scelto il nostro salone! Non vediamo l\'ora di vederti.')
                            ->helperText('Appare come testo introduttivo in tutte le email ai clienti. Usa {nome} per inserire automaticamente il nome del cliente.')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('email_footer_note')
                            ->label('Nota nel footer')
                            ->placeholder('es. Per qualsiasi informazione contattaci al numero...')
                            ->helperText('Appare in fondo a tutte le email, sotto i dati del salone. Puoi usare {nome} per il nome del cliente.')
                            ->rows(2)
                            ->columnSpanFull(),

                        ColorPicker::make('email_accent_color')
                            ->label('Colore header email (override)')
                            ->helperText('Lascia vuoto per usare automaticamente il colore del tema selezionato. Imposta solo se vuoi un colore diverso.')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Anteprima & Condivisione')->schema([
                        Textarea::make('meta_description')
                            ->label('Descrizione per la condivisione')
                            ->placeholder('es. Parrucchiere e centro estetico nel cuore di Milano. Prenota online.')
                            ->helperText('Testo che appare quando il link viene condiviso su WhatsApp, Facebook e Instagram. Lascia vuoto per usare automaticamente la tagline o la descrizione.')
                            ->maxLength(160)
                            ->rows(2)
                            ->columnSpanFull(),
                        Placeholder::make('preview_link')
                            ->label('')
                            ->content(function (): HtmlString {
                                $subdomain = \Filament\Facades\Filament::getTenant()?->subdomain;
                                $baseDomain = config('app.base_domain');
                                $url = ($subdomain && $baseDomain)
                                    ? 'http://' . $subdomain . '.' . $baseDomain . '/'
                                    : url('/');
                                return new HtmlString(
                                    '<a href="' . e($url) . '" target="_blank" class="text-primary-600 underline font-medium">Apri la vetrina pubblica →</a>'
                                );
                            }),
                    ]),
                ]),
            ]);
    }

    public function save(): void
    {
        $state   = $this->form->getState();
        $profile = SalonProfile::current();

        $days         = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $openingHours = [];
        foreach ($days as $day) {
            $type  = $state["hours_{$day}_type"] ?? 'closed';
            $entry = ['type' => $type];
            if ($type === 'continuous') {
                $entry['open_time']  = $state["hours_{$day}_open_time"]  ?? '09:00';
                $entry['close_time'] = $state["hours_{$day}_close_time"] ?? '19:00';
            } elseif ($type === 'split') {
                $entry['morning_open']    = $state["hours_{$day}_morning_open"]    ?? '09:00';
                $entry['morning_close']   = $state["hours_{$day}_morning_close"]   ?? '13:00';
                $entry['afternoon_open']  = $state["hours_{$day}_afternoon_open"]  ?? '15:00';
                $entry['afternoon_close'] = $state["hours_{$day}_afternoon_close"] ?? '19:30';
            }
            $openingHours[$day] = $entry;
        }

        $hourKeys = array_merge(...array_map(
            fn($d) => [
                "hours_{$d}_type",
                "hours_{$d}_open_time",
                "hours_{$d}_close_time",
                "hours_{$d}_morning_open",
                "hours_{$d}_morning_close",
                "hours_{$d}_afternoon_open",
                "hours_{$d}_afternoon_close",
            ],
            $days
        ));
        $profileData = Arr::except($state, [...$hourKeys, 'logo', 'cover', 'favicon', 'gallery']);
        $profileData['opening_hours'] = $openingHours;

        $profile->update($profileData);
        $profile->refresh();

        $this->form->saveRelationships();
        $this->mount();

        Notification::make()->title('Profilo salvato')->success()->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
