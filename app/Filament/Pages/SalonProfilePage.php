<?php

namespace App\Filament\Pages;

use App\Models\SalonProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class SalonProfilePage extends Page
{
    protected string $view = 'filament.pages.salon-profile';

    protected static ?string $navigationLabel = 'Profilo Salone';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 98;

    public ?array $data = [];

    public function mount(): void
    {
        $profile = SalonProfile::current();
        $hours   = $profile->opening_hours ?? [];

        $formData = [
            'name'                => $profile->name,
            'tagline'             => $profile->tagline,
            'primary_color'       => $profile->primary_color,
            'phone'               => $profile->phone,
            'address'             => $profile->address,
            'website'             => $profile->website,
            'description'         => $profile->description,
            'cancellation_policy' => $profile->cancellation_policy,
            'google_maps_embed'   => $profile->google_maps_embed,
            'instagram_url'       => $profile->instagram_url,
            'facebook_url'        => $profile->facebook_url,
            'tiktok_url'          => $profile->tiktok_url,
            'whatsapp_number'     => $profile->whatsapp_number,
        ];

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $formData["hours_{$day}_closed"] = $hours[$day]['closed'] ?? false;
            $formData["hours_{$day}_open"]   = $hours[$day]['open']   ?? '09:00';
            $formData["hours_{$day}_close"]  = $hours[$day]['close']  ?? '18:00';
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $profile = SalonProfile::current();

        $days = [
            'mon' => 'Lunedì',   'tue' => 'Martedì',  'wed' => 'Mercoledì',
            'thu' => 'Giovedì',  'fri' => 'Venerdì',  'sat' => 'Sabato',
            'sun' => 'Domenica',
        ];

        $hoursFields = [];
        foreach ($days as $key => $label) {
            $hoursFields[] = Grid::make(3)->schema([
                Toggle::make("hours_{$key}_closed")
                    ->label($label)
                    ->inline(false),
                TextInput::make("hours_{$key}_open")
                    ->label('Apertura')
                    ->placeholder('09:00'),
                TextInput::make("hours_{$key}_close")
                    ->label('Chiusura')
                    ->placeholder('18:00'),
            ]);
        }

        return $schema
            ->statePath('data')
            ->schema([
                Tabs::make()->tabs([

                    Tab::make('Identità')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nome del salone')
                                ->required(),
                            TextInput::make('tagline')
                                ->label('Tagline'),
                            ColorPicker::make('primary_color')
                                ->label('Colore primario')
                                ->required(),
                        ]),
                        Grid::make(2)->schema([
                            SpatieMediaLibraryFileUpload::make('logo')
                                ->label('Logo')
                                ->model($profile)
                                ->collection('logo')
                                ->image()
                                ->maxSize(2048),
                            SpatieMediaLibraryFileUpload::make('cover')
                                ->label('Immagine di copertina')
                                ->model($profile)
                                ->collection('cover')
                                ->image()
                                ->maxSize(5120),
                        ]),
                    ]),

                    Tab::make('Descrizione')->schema([
                        RichEditor::make('description')
                            ->label('Chi siamo')
                            ->columnSpanFull(),
                        RichEditor::make('cancellation_policy')
                            ->label('Politica di cancellazione')
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Galleria')->schema([
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label('Foto galleria')
                            ->model($profile)
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ]),

                    Tab::make('Orari')->schema($hoursFields),

                    Tab::make('Contatti & Social')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('phone')->label('Telefono'),
                            TextInput::make('website')->label('Sito web')->url(),
                        ]),
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
                    ]),

                    Tab::make('Anteprima')->schema([
                        Placeholder::make('preview_link')
                            ->label('')
                            ->content(new HtmlString(
                                '<a href="/" target="_blank" class="text-primary-600 underline font-medium">Apri la vetrina pubblica →</a>'
                            )),
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
            $openingHours[$day] = [
                'closed' => (bool) ($state["hours_{$day}_closed"] ?? false),
                'open'   => $state["hours_{$day}_open"]   ?? '09:00',
                'close'  => $state["hours_{$day}_close"]  ?? '18:00',
            ];
        }

        $hourKeys    = array_merge(...array_map(
            fn ($d) => ["hours_{$d}_closed", "hours_{$d}_open", "hours_{$d}_close"],
            $days
        ));
        $profileData = Arr::except($state, [...$hourKeys, 'logo', 'cover', 'gallery']);
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
