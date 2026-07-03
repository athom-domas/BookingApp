<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceCategoryResource\Pages;
use App\Models\Business;
use App\Models\ServiceCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServiceCategoryResource extends Resource
{
    protected static ?string $model = ServiceCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'categoria';
    protected static ?string $pluralModelLabel = 'categorie';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() || ! auth()->user()?->isStaff();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->unique(
                            table: ServiceCategory::class,
                            column: 'name',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule) => $rule->where('business_id', Business::currentId()),
                        )
                        ->maxLength(255)
                        ->validationMessages([
                            'required' => 'Il nome della categoria è obbligatorio.',
                            'unique'   => 'Esiste già una categoria con questo nome.',
                            'max'      => 'Il nome non può superare 255 caratteri.',
                        ]),

                    Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Impostazioni')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Attiva')
                        ->default(true),
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

                TextColumn::make('services_count')
                    ->label('Servizi')
                    ->counts('services')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Attiva'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCategories::route('/'),
            'edit'  => Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
