<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\ProductOrderItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'prodotto';
    protected static ?string $pluralModelLabel = 'prodotti';

    public static function canViewAny(): bool
    {
        return ! auth()->user()?->isStaff();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (! (auth()->user()?->isAdmin() ?? false)) {
            return false;
        }
        return ! ProductOrderItem::where('product_id', $record->id)->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informazioni')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('price')
                        ->label('Prezzo (€)')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01),

                    SpatieMediaLibraryFileUpload::make('photo')
                        ->label('Foto prodotto')
                        ->collection('photo')
                        ->image()
                        ->maxSize(4096)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Scorte')
                ->schema([
                    TextInput::make('stock')
                        ->label('Scorte disponibili')
                        ->required()
                        ->numeric()
                        ->integer()
                        ->minValue(0),

                    TextInput::make('low_stock_threshold')
                        ->label('Soglia scorte basse')
                        ->helperText('Ricevi una notifica quando le scorte scendono a questo livello. Lascia vuoto per disabilitare.')
                        ->numeric()
                        ->integer()
                        ->minValue(0),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Visibilità')
                ->schema([
                    Toggle::make('in_sale')
                        ->label('In vendita')
                        ->helperText('Mostra il prodotto nella pagina clienti')
                        ->default(false),

                    Toggle::make('active')
                        ->label('Attivo')
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
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label('Foto')
                    ->collection('photo')
                    ->conversion('thumb')
                    ->width(48)
                    ->height(48),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Scorte')
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record): string => $record->isBelowThreshold() ? 'danger' : 'success'),

                ToggleColumn::make('in_sale')
                    ->label('In vendita'),

                ToggleColumn::make('active')
                    ->label('Attivo'),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Attivo')->boolean()
                    ->trueLabel('Solo attivi')->falseLabel('Solo inattivi'),
                TernaryFilter::make('in_sale')->label('In vendita')->boolean()
                    ->trueLabel('In vendita')->falseLabel('Non in vendita'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (Product $record, DeleteAction $action) {
                        if (ProductOrderItem::where('product_id', $record->id)->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Impossibile eliminare')
                                ->body('Il prodotto ha ordini associati. Disattivalo invece di eliminarlo.')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
