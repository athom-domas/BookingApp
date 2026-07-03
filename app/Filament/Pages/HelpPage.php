<?php

namespace App\Filament\Pages;

use App\Filament\Pages\IntegrationSettings;
use App\Filament\Pages\StripeConnectPage;
use App\Models\Business;
use App\Models\IntegrationSetting;
use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $navigationLabel = 'Aiuto';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $slug = 'aiuto';
    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.help';

    public array $integrationStatuses = [];
    public string $integrationSettingsUrl = '';
    public string $stripeConnectUrl = '';

    public function mount(): void
    {
        $setting = IntegrationSetting::current();

        $this->integrationSettingsUrl = IntegrationSettings::getUrl();
        $this->stripeConnectUrl = StripeConnectPage::getUrl();

        $business = Business::find(app()->bound('current_business_id') ? app('current_business_id') : null);

        $this->integrationStatuses = [
            'stripe' => [
                'label'      => 'Stripe',
                'configured' => $business?->canAcceptOnlinePayments() ?? false,
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
