<?php

namespace App\Filament\Pages;

use App\Models\IntegrationSetting;
use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $navigationLabel = 'Aiuto';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $slug = 'aiuto';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.help';

    public array $integrationStatuses = [];
    public string $integrationSettingsUrl = '';

    public function mount(): void
    {
        $setting = IntegrationSetting::current();

        $this->integrationSettingsUrl = IntegrationSettings::getUrl();

        $this->integrationStatuses = [
            'stripe' => [
                'label'      => 'Stripe',
                'configured' => ! empty($setting->stripe_public_key) && ! empty($setting->stripe_secret_key),
            ],
            'whatsapp' => [
                'label'      => 'WhatsApp',
                'configured' => IntegrationSetting::hasMetaWhatsApp(),
            ],
            'google_calendar' => [
                'label'      => 'Google Calendar',
                'configured' => ! empty($setting->google_calendar_id) && ! empty($setting->google_credentials_json),
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
