<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Clienti';

    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clienti';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.view'))) ?? false;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.view'))) ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.create'))) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.edit'))) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.delete'))) ?? false;
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.delete'))) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn(Builder $query): Builder => $query
                ->where('name', 'customer')
                ->where('guard_name', 'web'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->unique(User::class, 'email', ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->required()
                ->dehydrated(fn(?string $state): bool => filled($state))
                ->minLength(8)
                ->maxLength(255)
                ->visibleOn('create'),

            Textarea::make('internal_notes')
                ->label('Note interne')
                ->rows(8)
                ->columnSpanFull()
                ->helperText("Visibili solo nell'area admin. Non vengono mostrate al cliente."),

            Section::make('Preferenze notifiche')
                ->schema([
                    Group::make()
                        ->relationship('preferences')
                        ->schema([
                            Select::make('notification_channel')
                                ->label('Canale notifiche')
                                ->options([
                                    'email'    => 'Email',
                                    'sms'      => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('email')
                                ->required()
                                ->live(),

                            TextInput::make('phone_number')
                                ->label('Numero di telefono')
                                ->tel()
                                ->placeholder('+39 333 123 4567')
                                ->visible(
                                    fn(Get $get): bool =>
                                    in_array($get('notification_channel'), ['sms', 'whatsapp'])
                                ),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registrato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->label('Scheda cliente'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
