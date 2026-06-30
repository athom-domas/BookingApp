<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use App\Models\ServiceCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Validation\Rule;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'servizio';
    protected static ?string $pluralModelLabel = 'servizi';

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
                            table: Service::class,
                            column: 'name',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule) => $rule->where('business_id', \App\Models\Business::currentId()),
                        )
                        ->maxLength(255)
                        ->validationMessages([
                            'required' => 'Il nome del servizio è obbligatorio.',
                            'unique'   => 'Esiste già un servizio con questo nome.',
                            'max'      => 'Il nome non può superare 255 caratteri.',
                        ]),

                    Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('duration_minutes')
                        ->label('Durata (minuti)')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->integer()
                        ->validationMessages([
                            'required' => 'La durata è obbligatoria.',
                            'numeric'  => 'La durata deve essere un numero.',
                            'min'      => 'La durata deve essere di almeno 1 minuto.',
                            'integer'  => 'La durata deve essere un numero intero.',
                        ]),

                    TextInput::make('price')
                        ->label('Prezzo (€)')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->validationMessages([
                            'required' => 'Il prezzo è obbligatorio.',
                            'numeric'  => 'Il prezzo deve essere un numero.',
                            'min'      => 'Il prezzo deve essere maggiore di zero.',
                        ]),

                    Select::make('service_category_id')
                        ->label('Categoria')
                        ->options(fn () => ServiceCategory::orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->placeholder('Nessuna categoria')
                        ->rules([
                            'nullable',
                            Rule::exists('service_categories', 'id')
                                ->where('business_id', \App\Models\Business::currentId()),
                        ])
                        ->hidden(fn () => ServiceCategory::count() === 0)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Immagine')
                ->schema([
                    FileUpload::make('image_path')
                        ->label('Foto del servizio')
                        ->disk('public')
                        ->image()
                        ->maxSize(10240)
                        ->saveUploadedFileUsing(fn ($file) => \App\PageBlocks\AbstractPageBlock::storeAsWebp($file, 'site-builder/services'))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Impostazioni')
                ->schema([
                    Toggle::make('active')
                        ->label('Attivo')
                        ->default(true),

                    Toggle::make('featured')
                        ->label('In evidenza')
                        ->helperText('Mostrato in primo piano nella pagina del salone.'),
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

                TextColumn::make('duration_minutes')
                    ->label('Durata (min)')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Prezzo')
                    ->money('EUR')
                    ->sortable(),

                ToggleColumn::make('active')
                    ->label('Attivo'),

                ToggleColumn::make('featured')
                    ->label('In evidenza'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('active')
                    ->label('Attivo')
                    ->boolean()
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo inattivi'),
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
            'index' => Pages\ListServices::route('/'),
            'edit'  => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
