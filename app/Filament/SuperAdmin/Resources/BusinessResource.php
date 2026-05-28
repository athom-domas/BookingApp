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
use Filament\Tables\Actions\EditAction;
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
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('subdomain')->label('Sottodominio'),
                TextColumn::make('status')->label('Stato')->badge()
                    ->color(fn(BusinessStatus $state) => match ($state) {
                        BusinessStatus::Active    => 'success',
                        BusinessStatus::Suspended => 'danger',
                    }),
                TextColumn::make('created_at')->label('Creato')->since()->sortable(),
            ])
            ->actions([EditAction::make()]);
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
}
