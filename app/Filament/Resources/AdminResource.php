<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Amministratori';

    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'amministratore';

    protected static ?string $pluralModelLabel = 'amministratori';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('roles')
            ->whereHas('roles', fn(Builder $query): Builder => $query
                ->where('name', 'admin')
                ->where('guard_name', 'web'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Dati personali')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255)
                        ->validationMessages([
                            'required' => 'Il nome è obbligatorio.',
                            'max'      => 'Il nome non può superare 255 caratteri.',
                        ]),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->maxLength(255)
                        ->validationMessages([
                            'required' => 'L\'email è obbligatoria.',
                            'email'    => 'Inserisci un indirizzo email valido.',
                            'unique'   => 'Questa email è già in uso.',
                            'max'      => 'L\'email non può superare 255 caratteri.',
                        ]),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Account')
                ->schema([
                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->confirmed()
                        ->required(fn(string $operation): bool => $operation === 'create')
                        ->dehydrated(fn(?string $state): bool => filled($state))
                        ->minLength(8)
                        ->maxLength(255)
                        ->validationMessages([
                            'required'  => 'La password è obbligatoria.',
                            'confirmed' => 'Le password non corrispondono.',
                            'min'       => 'La password deve contenere almeno 8 caratteri.',
                            'max'       => 'La password non può superare 255 caratteri.',
                        ]),

                    TextInput::make('password_confirmation')
                        ->label('Conferma password')
                        ->password()
                        ->required(fn(string $operation): bool => $operation === 'create')
                        ->dehydrated(false)
                        ->maxLength(255)
                        ->validationMessages([
                            'required' => 'La conferma password è obbligatoria.',
                            'max'      => 'La conferma password non può superare 255 caratteri.',
                        ]),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Impostazioni')
                ->schema([
                    Toggle::make('works_as_staff')
                        ->label('Lavora anche come staff')
                        ->helperText('Quando attivo, questo admin appare come personale prenotabile dai clienti.')
                        ->live()
                        ->columnSpanFull(),

                    SpatieMediaLibraryFileUpload::make('avatar')
                        ->label('Foto profilo')
                        ->collection('avatar')
                        ->image()
                        ->maxSize(2048)
                        ->visible(fn(Get $get): bool => (bool) $get('works_as_staff'))
                        ->columnSpanFull(),

                    Select::make('services')
                        ->label('Servizi erogati')
                        ->relationship(
                            name: 'services',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn(Builder $query): Builder => $query->where('active', true)->orderBy('name'),
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->visible(fn(Get $get): bool => (bool) $get('works_as_staff'))
                        ->helperText('Seleziona almeno un servizio per rendere lo staff prenotabile dal portale clienti.')
                        ->columnSpanFull(),

                    Toggle::make('receive_email_notifications')
                        ->label('Ricevi notifiche email')
                        ->helperText('Invia una email per ogni nuova prenotazione ricevuta nel sistema.')
                        ->default(true),
                ])
                ->columns(2)
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

                TextColumn::make('staff_badge')
                    ->label('Ruolo staff')
                    ->getStateUsing(fn(User $record): string => $record->hasRole('staff') ? 'Staff' : '—')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Staff' ? 'success' : 'gray'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Modifica'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit'   => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
