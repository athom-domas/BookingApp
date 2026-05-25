<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StaffResource\Pages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff';

    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'membro staff';

    protected static ?string $pluralModelLabel = 'staff';

    public static function canViewAny(): bool
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
        if ($record instanceof \App\Models\User && $record->isAdmin()) {
            return false;
        }
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('roles')
            ->whereHas('roles', fn(Builder $query): Builder => $query
                ->where('name', 'staff')
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
                ->confirmed()
                ->required(fn(string $operation): bool => $operation === 'create')
                ->dehydrated(fn(?string $state): bool => filled($state))
                ->minLength(8)
                ->maxLength(255),

            TextInput::make('password_confirmation')
                ->label('Conferma password')
                ->password()
                ->required(fn(string $operation): bool => $operation === 'create')
                ->dehydrated(false)
                ->maxLength(255),

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
                ->helperText('Seleziona almeno un servizio per rendere lo staff prenotabile dal portale clienti.')
                ->columnSpanFull(),

            ColorPicker::make('calendar_color')
                ->label('Colore calendario')
                ->default(fn() => collect([
                    '#3B82F6',
                    '#10B981',
                    '#F59E0B',
                    '#EF4444',
                    '#8B5CF6',
                    '#EC4899',
                    '#14B8A6',
                    '#F97316',
                ])->random()),

            SpatieMediaLibraryFileUpload::make('avatar')
                ->label('Foto profilo')
                ->collection('avatar')
                ->image()
                ->maxSize(2048),

            Textarea::make('bio')
                ->label('Bio')
                ->rows(3)
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
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'               => Pages\ListStaff::route('/'),
            'create'              => Pages\CreateStaff::route('/create'),
            'edit'                => Pages\EditStaff::route('/{record}/edit'),
            'manage-availability' => Pages\ManageAvailability::route('/{record}/availability'),
        ];
    }
}
