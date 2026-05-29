<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\BusinessStatus;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\CreateBusiness;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\EditBusiness;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\ListBusinesses;
use App\Models\Business;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;
    protected static ?string $navigationLabel = 'Saloni';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    private const RESERVED = [
        'superadmin', 'admin', 'api', 'www', 'app',
        'mail', 'static', 'assets', 'media', 'webhook', 'health',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
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

            TextInput::make('admin_email')
                ->label('Email admin iniziale')
                ->email()
                ->required()
                ->visibleOn('create'),

            Select::make('status')
                ->label('Stato')
                ->options(['active' => 'Attivo', 'suspended' => 'Sospeso'])
                ->required()
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('subscriptions'))
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('subdomain')->label('Sottodominio'),
                TextColumn::make('status')->label('Stato')->badge()
                    ->color(fn(BusinessStatus $state) => match ($state) {
                        BusinessStatus::Active    => 'success',
                        BusinessStatus::Suspended => 'danger',
                    }),
                TextColumn::make('created_at')->label('Creato')->since()->sortable(),

                TextColumn::make('subscriptionStatus')
                    ->label('Abbonamento')
                    ->badge()
                    ->state(fn (Business $record): string => match ($record->subscriptionStatus()) {
                        'trial'        => 'Trial',
                        'active'       => 'Attivo',
                        'grace_period' => 'Grace period',
                        'expired'      => 'Scaduto',
                    })
                    ->color(fn (string $state): string => match ($state) {
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
                    ->state(fn (Business $record): string => $record->pm_type
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
                    ->visible(fn (Business $record): bool => in_array($record->subscriptionStatus(), ['trial', 'expired']))
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
                    ->visible(fn (Business $record): bool => $record->subscriptionStatus() === 'active')
                    ->requiresConfirmation()
                    ->modalDescription('L\'accesso verrà revocato immediatamente. Usare solo in casi eccezionali.')
                    ->action(function (Business $record): void {
                        $record->subscription('default')->cancelNow();

                        Notification::make()
                            ->title('Abbonamento cancellato immediatamente.')
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
            ]);
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
