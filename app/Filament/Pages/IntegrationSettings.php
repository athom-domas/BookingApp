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
            'twilio_sid'               => $setting->twilio_sid,
            'twilio_token'             => $setting->twilio_token,
            'twilio_from'              => $setting->twilio_from,
            'google_calendar_id'       => $setting->google_calendar_id,
            'google_credentials_json'  => $setting->google_credentials_json,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Stripe')
                    ->schema([
                        TextInput::make('stripe_public_key')
                            ->label('Chiave pubblica (pk_...)')
                            ->helperText('Usata nel frontend per Stripe.js')
                            ->nullable(),

                        TextInput::make('stripe_secret_key')
                            ->label('Chiave segreta (sk_...)')
                            ->password()
                            ->revealable()
                            ->nullable(),

                        TextInput::make('stripe_webhook_secret')
                            ->label('Webhook secret (whsec_...)')
                            ->helperText('Impostalo nel Stripe Dashboard → Webhook dopo aver registrato l\'endpoint')
                            ->password()
                            ->revealable()
                            ->nullable(),
                    ]),

                Section::make('Twilio')
                    ->schema([
                        TextInput::make('twilio_sid')
                            ->label('Account SID')
                            ->password()
                            ->revealable()
                            ->nullable(),

                        TextInput::make('twilio_token')
                            ->label('Auth Token')
                            ->password()
                            ->revealable()
                            ->nullable(),

                        TextInput::make('twilio_from')
                            ->label('Numero mittente')
                            ->helperText('Es. +393331234567')
                            ->nullable(),
                    ]),

                Section::make('Google Calendar')
                    ->schema([
                        TextInput::make('google_calendar_id')
                            ->label('Calendar ID')
                            ->helperText('Es. abc123@group.calendar.google.com')
                            ->nullable(),

                        Textarea::make('google_credentials_json')
                            ->label('Credenziali Service Account (JSON)')
                            ->helperText('Incolla il contenuto del file JSON scaricato da Google Cloud Console → Service Accounts → Chiavi')
                            ->nullable()
                            ->rows(6),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = array_filter($this->form->getState(), fn ($v) => $v !== null);
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
