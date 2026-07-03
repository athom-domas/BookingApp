<?php

namespace App\Filament\Pages;

use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IntegrationSettings extends Page
{
    protected string $view = 'filament.pages.integration-settings';

    protected static ?string $navigationLabel = 'Integrazioni';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static string|\UnitEnum|null $navigationGroup = 'Configurazioni';
    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = IntegrationSetting::current();
        $this->form->fill([
            'stripe_public_key'                 => $setting->stripe_public_key,
            'stripe_secret_key'                 => $setting->stripe_secret_key,
            'stripe_webhook_secret'             => $setting->stripe_webhook_secret,
            'meta_whatsapp_token'               => $setting->meta_whatsapp_token,
            'meta_whatsapp_phone_id'            => $setting->meta_whatsapp_phone_id,
            'meta_whatsapp_template'            => $setting->meta_whatsapp_template ?? 'appointment_reminder',
            'google_calendar_id'                => $setting->google_calendar_id,
            'google_credentials_json'           => $setting->google_credentials_json,
            'whatsapp_ai_enabled'               => $setting->whatsapp_ai_enabled ?? false,
            'whatsapp_ai_booking_enabled'       => $setting->whatsapp_ai_booking_enabled ?? true,
            'whatsapp_ai_cancellation_enabled'  => $setting->whatsapp_ai_cancellation_enabled ?? false,
            'whatsapp_ai_custom_instructions'   => $setting->whatsapp_ai_custom_instructions,
            'whatsapp_ai_handoff_email'         => $setting->whatsapp_ai_handoff_email,
            'whatsapp_ai_max_turns'             => $setting->whatsapp_ai_max_turns ?? 12,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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

                Section::make('Assistente WhatsApp (AI)')
                    ->description('Abilita un assistente conversazionale AI per ricevere prenotazioni via WhatsApp. Richiede le credenziali Meta WhatsApp configurate sopra.')
                    ->schema([
                        Toggle::make('whatsapp_ai_enabled')
                            ->label('Assistente WhatsApp attivo')
                            ->helperText('Attiva il bot AI per rispondere ai messaggi in arrivo su WhatsApp.'),

                        Toggle::make('whatsapp_ai_booking_enabled')
                            ->label('Permetti prenotazione via WhatsApp')
                            ->default(true),

                        Toggle::make('whatsapp_ai_cancellation_enabled')
                            ->label('Permetti cancellazione via WhatsApp')
                            ->helperText('Se disabilitato, il bot non potrà cancellare appuntamenti. Abilitare solo dopo aver testato il flusso.')
                            ->default(false),

                        TextInput::make('whatsapp_ai_handoff_email')
                            ->label('Email notifica escalation staff')
                            ->helperText('Indirizzo a cui inviare la notifica quando il bot trasferisce a un operatore umano.')
                            ->email()
                            ->nullable(),

                        TextInput::make('whatsapp_ai_max_turns')
                            ->label('Numero max turni')
                            ->helperText('Limite di messaggi per conversazione prima di invitare il cliente a contattare direttamente il salone. Default: 12.')
                            ->numeric()
                            ->default(12)
                            ->minValue(4)
                            ->maxValue(50),

                        Textarea::make('whatsapp_ai_custom_instructions')
                            ->label('Istruzioni personalizzate')
                            ->helperText('Personalizza tono e identità dell\'assistente (es. "Usa un tono caloroso e chiama il salone Atelier Rossi"). Non può sovrascrivere le regole di sicurezza.')
                            ->rows(4)
                            ->nullable(),

                        Placeholder::make('webhook_url')
                            ->label('URL webhook da registrare su Meta Developer Console')
                            ->content(fn () => url('/whatsapp/webhook'))
                            ->helperText('Subscribed fields: messages'),
                    ]),

                Section::make('Stato connessione WhatsApp')
                    ->description('Informazioni di sola lettura sullo stato della connessione WhatsApp.')
                    ->schema([
                        Placeholder::make('status_token')
                            ->label('Token Meta')
                            ->content(fn () => IntegrationSetting::current()->meta_whatsapp_token ? 'presente' : 'assente'),

                        Placeholder::make('status_phone_id')
                            ->label('Phone ID')
                            ->content(fn () => IntegrationSetting::current()->meta_whatsapp_phone_id ? 'presente' : 'assente'),

                        Placeholder::make('status_ai_enabled')
                            ->label('AI abilitata')
                            ->content(fn () => IntegrationSetting::current()->whatsapp_ai_enabled ? 'sì' : 'no'),

                        Placeholder::make('status_notifications_enabled')
                            ->label('Notifiche WhatsApp (gestite dalla piattaforma)')
                            ->content(fn () => IntegrationSetting::current()->whatsapp_notifications_enabled ? 'abilitate' : 'non abilitate'),

                        Placeholder::make('status_monthly_usage')
                            ->label('Messaggi notifica questo mese')
                            ->content(function () {
                                $s     = IntegrationSetting::current();
                                $limit = $s->whatsapp_monthly_limit ? (string) $s->whatsapp_monthly_limit : '∞';

                                return ($s->whatsapp_monthly_sent ?? 0) . ' / ' . $limit;
                            }),

                        Placeholder::make('status_last_outbound')
                            ->label('Ultimo messaggio inviato')
                            ->content(function () {
                                $businessId = IntegrationSetting::current()->business_id;
                                $msg        = WhatsAppMessage::where('business_id', $businessId)
                                    ->where('direction', 'outbound')
                                    ->latest()
                                    ->first();
                                return $msg ? $msg->created_at->format('d/m/Y H:i') : 'nessuno';
                            }),

                        Placeholder::make('status_last_inbound')
                            ->label('Ultimo webhook ricevuto')
                            ->content(function () {
                                $businessId = IntegrationSetting::current()->business_id;
                                $msg        = WhatsAppMessage::where('business_id', $businessId)
                                    ->where('direction', 'inbound')
                                    ->latest()
                                    ->first();
                                return $msg ? $msg->created_at->format('d/m/Y H:i') : 'nessuno';
                            }),

                        Placeholder::make('status_last_error')
                            ->label('Ultimo errore')
                            ->content(function () {
                                $businessId = IntegrationSetting::current()->business_id;
                                $msg        = WhatsAppMessage::where('business_id', $businessId)
                                    ->whereNotNull('error_code')
                                    ->latest()
                                    ->first();
                                return $msg ? "[{$msg->error_code}] " . ($msg->error_message ?? 'nessun dettaglio') : 'nessuno';
                            }),
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
