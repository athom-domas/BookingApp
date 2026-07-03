<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\BusinessStatus;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\CreateBusiness;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\EditBusiness;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\ListBusinesses;
use App\Models\Business;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;
    protected static ?string $navigationLabel = 'Saloni';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    private const RESERVED = [
        'superadmin',
        'admin',
        'api',
        'www',
        'app',
        'mail',
        'static',
        'assets',
        'media',
        'webhook',
        'health',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni salone')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome salone')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('subdomain')
                        ->label('Sottodominio')
                        ->required()
                        ->maxLength(63)
                        ->helperText('Solo lettere minuscole, numeri e trattini.')
                        ->rules([
                            'alpha_dash',
                            fn() => function ($attribute, $value, $fail) {
                                if (in_array(strtolower((string) $value), self::RESERVED)) {
                                    $fail("Il sottodominio '{$value}' è riservato.");
                                }
                            },
                        ]),

                    Select::make('status')
                        ->label('Stato')
                        ->options(['active' => 'Attivo', 'suspended' => 'Sospeso'])
                        ->required()
                        ->visibleOn('edit'),

                    TextInput::make('stripe_platform_fee_percent')
                        ->label('Fee piattaforma (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.1)
                        ->placeholder(fn() => (SystemSetting::getStripePlatformFeePercent() ?? config('services.stripe.platform_fee_percent', 0)) . ' (default globale)')
                        ->helperText('Lascia vuoto per usare la fee globale configurata in Stripe Connect; se assente usa STRIPE_PLATFORM_FEE_PERCENT.')
                        ->visibleOn('edit'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Admin iniziale')
                ->schema([
                    Radio::make('admin_type')
                        ->label('')
                        ->options(['new' => 'Nuovo admin', 'existing' => 'Admin esistente'])
                        ->default('new')
                        ->live()
                        ->inline()
                        ->columnSpanFull(),

                    TextInput::make('admin_email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique('users', 'email')
                        ->hidden(fn(Get $get) => $get('admin_type') !== 'new')
                        ->columnSpanFull(),

                    Select::make('admin_existing_id')
                        ->label('Seleziona admin')
                        ->options(fn() => User::role('admin')
                            ->get()
                            ->mapWithKeys(fn(User $u) => [$u->id => "{$u->name} ({$u->email})"]))
                        ->searchable()
                        ->required()
                        ->hidden(fn(Get $get) => $get('admin_type') !== 'existing')
                        ->columnSpanFull(),
                ])
                ->visibleOn('create')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['subscriptions', 'admins']))
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('first_admin')
                    ->label('Admin')
                    ->state(fn(Business $record): string => $record->admins->first()?->email ?? '—')
                    ->searchable(query: fn($query, string $search) => $query->orWhereHas(
                        'admins',
                        fn($q) => $q->where('users.email', 'like', "%{$search}%")
                    )),
                TextColumn::make('status')->label('Stato')->badge()
                    ->color(fn(BusinessStatus $state) => match ($state) {
                        BusinessStatus::Active    => 'success',
                        BusinessStatus::Suspended => 'danger',
                    }),
                TextColumn::make('created_at')->label('Creato')->since()->sortable(),

                TextColumn::make('subscriptionStatus')
                    ->label('Abbonamento')
                    ->badge()
                    ->state(fn(Business $record): string => match ($record->subscriptionStatus()) {
                        'trial'        => 'Trial',
                        'active'       => 'Attivo',
                        'grace_period' => 'Grace period',
                        'expired'      => 'Scaduto',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Trial'        => 'warning',
                        'Attivo'       => 'success',
                        'Grace period' => 'warning',
                        'Scaduto'      => 'danger',
                        default        => 'gray',
                    }),

                TextColumn::make('trial_ends_at')
                    ->label('Fine trial')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('pm_last_four')
                    ->label('Pagamento')
                    ->state(
                        fn(Business $record): string => $record->pm_type
                            ? ucfirst($record->pm_type) . ' ••••' . $record->pm_last_four
                            : '—'
                    )
                    ->toggleable(),
            ])
            ->actions([
                Action::make('extendTrial')
                    ->label('Estendi trial')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->visible(fn(Business $record): bool => in_array($record->subscriptionStatus(), ['trial', 'expired']))
                    ->form([
                        \Filament\Forms\Components\TextInput::make('days')
                            ->label('Giorni aggiuntivi')
                            ->numeric()
                            ->default(14)
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                    ])
                    ->action(function (Business $record, array $data): void {
                        $base = ($record->trial_ends_at && $record->trial_ends_at->isFuture())
                            ? $record->trial_ends_at
                            : now();
                        $record->update(['trial_ends_at' => $base->addDays((int) $data['days'])]);

                        Notification::make()
                            ->title("Trial esteso di {$data['days']} giorni.")
                            ->success()
                            ->send();
                    }),

                Action::make('cancelSubscriptionNow')
                    ->label('Cancella abbonamento')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Business $record): bool => $record->subscriptionStatus() === 'active')
                    ->requiresConfirmation()
                    ->modalDescription('L\'accesso verrà revocato immediatamente. Usare solo in casi eccezionali.')
                    ->action(function (Business $record): void {
                        $record->subscription('default')->cancelNow();

                        Notification::make()
                            ->title('Abbonamento cancellato immediatamente.')
                            ->success()
                            ->send();
                    }),

                Action::make('whatsappNotifications')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->fillForm(function (Business $record): array {
                        $setting = $record->integrationSetting;

                        return [
                            'whatsapp_notifications_enabled' => (bool) $setting?->whatsapp_notifications_enabled,
                            'whatsapp_monthly_limit'         => $setting?->whatsapp_monthly_limit,
                        ];
                    })
                    ->form([
                        \Filament\Forms\Components\Toggle::make('whatsapp_notifications_enabled')
                            ->label('Notifiche WhatsApp abilitate'),
                        \Filament\Forms\Components\TextInput::make('whatsapp_monthly_limit')
                            ->label('Limite messaggi mensile')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Illimitato'),
                        \Filament\Forms\Components\Placeholder::make('sent_info')
                            ->label('Inviati questo mese')
                            ->content(fn (Business $record): string => (string) ($record->integrationSetting?->whatsapp_monthly_sent ?? 0)),
                    ])
                    ->action(function (Business $record, array $data): void {
                        \App\Models\IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
                            ['business_id' => $record->id],
                            [
                                'whatsapp_notifications_enabled' => (bool) ($data['whatsapp_notifications_enabled'] ?? false),
                                'whatsapp_monthly_limit'         => filled($data['whatsapp_monthly_limit'] ?? null)
                                    ? (int) $data['whatsapp_monthly_limit']
                                    : null,
                            ]
                        );

                        Notification::make()
                            ->title('Impostazioni WhatsApp aggiornate.')
                            ->success()
                            ->send();
                    }),

                Action::make('storefront')
                    ->label('Vetrina')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn(Business $record): string => self::storefrontUrl($record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Tutti i dati del salone (appuntamenti, utenti, impostazioni) verranno eliminati definitivamente.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers\BusinessAdminsRelationManager::class,
            \App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers\ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit'   => EditBusiness::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    private static function storefrontUrl(Business $record): string
    {
        $baseDomain = config('app.base_domain');

        return $baseDomain
            ? 'http://' . $record->subdomain . '.' . $baseDomain . '/'
            : url('/admin/' . $record->subdomain . '/');
    }
}
