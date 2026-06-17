<?php

namespace App\Filament\Pages;

use App\Models\IntegrationSetting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IntegrationSettings extends Page
{
    protected string $view = 'filament.pages.integration-settings';

    protected static ?string $navigationLabel = 'Integrazioni';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = IntegrationSetting::current();
        $this->form->fill([
            'stripe_public_key'        => $setting->stripe_public_key,
            'stripe_secret_key'        => $setting->stripe_secret_key,
            'stripe_webhook_secret'    => $setting->stripe_webhook_secret,
            'meta_whatsapp_token'      => $setting->meta_whatsapp_token,
            'meta_whatsapp_phone_id'   => $setting->meta_whatsapp_phone_id,
            'meta_whatsapp_template'   => $setting->meta_whatsapp_template ?? 'appointment_reminder',
            'google_calendar_id'       => $setting->google_calendar_id,
            'google_credentials_json'  => $setting->google_credentials_json,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Stripe')
                    ->description('Credenziali per accettare pagamenti online. Trovale su dashboard.stripe.com → Sviluppatori → Chiavi API.')
                    ->schema([
                        TextInput::make('stripe_public_key')
                            ->label('Chiave pubblica (pk_...)')
                            ->helperText('Inizia con pk_live_ (produzione) o pk_test_ (test). Visibile nella pagina Chiavi API.')
                            ->nullable(),

                        TextInput::make('stripe_secret_key')
                            ->label('Chiave segreta (sk_...)')
                            ->helperText('Inizia con sk_live_ (produzione) o sk_test_ (test). Visibile solo al momento della creazione.')
                            ->password()
                            ->revealable()
                            ->nullable(),

                        TextInput::make('stripe_webhook_secret')
                            ->label('Webhook secret (whsec_...)')
                            ->helperText('dashboard.stripe.com → Sviluppatori → Webhook → seleziona l\'endpoint → "Firma segreta". Generato dopo aver registrato l\'URL del webhook.')
                            ->password()
                            ->revealable()
                            ->nullable(),
                    ]),

                Section::make('WhatsApp (Meta Cloud API)')
                    ->description('Credenziali per inviare promemoria via WhatsApp. Richiede un\'app Meta con WhatsApp Business API configurata. Consulta Aiuto → SMS e WhatsApp per la guida completa.')
                    ->schema([
                        TextInput::make('meta_whatsapp_token')
                            ->label('Access Token')
                            ->helperText('Token permanente del System User. Meta Business Suite → Impostazioni → Utenti di sistema → Genera token.')
                            ->password()
                            ->revealable()
                            ->nullable(),

                        TextInput::make('meta_whatsapp_phone_id')
                            ->label('Phone Number ID')
                            ->helperText('Meta for Developers → App → WhatsApp → Configurazione API → Phone Number ID (stringa numerica).')
                            ->nullable(),

                        TextInput::make('meta_whatsapp_template')
                            ->label('Nome template')
                            ->helperText('Nome del template approvato da Meta per i promemoria. Default: appointment_reminder.')
                            ->nullable()
                            ->placeholder('appointment_reminder'),
                    ]),

                Section::make('Google Calendar')
                    ->description('Credenziali per sincronizzare gli appuntamenti con Google Calendar. Richiede un Service Account su Google Cloud Console.')
                    ->schema([
                        TextInput::make('google_calendar_id')
                            ->label('Calendar ID')
                            ->helperText('Google Calendar → Impostazioni del calendario → "ID calendario". Es. abc123@group.calendar.google.com. Il Service Account deve avere il ruolo "Modifica eventi" sul calendario.')
                            ->nullable(),

                        Textarea::make('google_credentials_json')
                            ->label('Credenziali Service Account (JSON)')
                            ->helperText('console.cloud.google.com → IAM e amministrazione → Account di servizio → seleziona account → Chiavi → Aggiungi chiave → JSON. Incolla qui l\'intero contenuto del file scaricato.')
                            ->nullable()
                            ->rows(6),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = array_filter($this->form->getState(), fn ($v) => $v !== null && $v !== '');
        IntegrationSetting::current()->update($data);

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
