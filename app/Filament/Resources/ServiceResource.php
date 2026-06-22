<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
    protected static ?int $navigationSort = 2;
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
                        ->unique(Service::class, 'name', ignoreRecord: true)
                        ->maxLength(255),

                    Textarea::make('description')
                        ->label('Descrizione')
                        ->rows(3)
                        ->columnSpanFull(),

                    TextInput::make('duration_minutes')
                        ->label('Durata (minuti)')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->integer(),

                    TextInput::make('price')
                        ->label('Prezzo (€)')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01),
                ])
                ->columns(2)
                ->columnSpanFull(),

            Section::make('Impostazioni')
                ->schema([
                    Toggle::make('active')
                        ->label('Attivo')
                        ->default(true),

                    Toggle::make('featured')
                        ->label('In evidenza')
                        ->helperText('Mostrato in primo piano nella pagina del salone. Massimo 4 servizi.')
                        ->live()
                        ->afterStateUpdated(function (bool $state, $set, $record): void {
                            if (! $state) return;
                            $count = Service::where('featured', true)
                                ->when($record?->id, fn ($q, $id) => $q->where('id', '!=', $id))
                                ->count();
                            if ($count >= 4) {
                                $set('featured', false);
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('Limite raggiunto')
                                    ->body('Puoi selezionare al massimo 4 servizi in evidenza.')
                                    ->send();
                            }
                        }),
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
                    ->label('In evidenza')
                    ->disabled(fn (Service $record): bool => ! $record->featured && Service::where('featured', true)->count() >= 4)
                    ->updateStateUsing(function (Service $record, bool $state): bool {
                        if ($state && Service::where('featured', true)->where('id', '!=', $record->id)->count() >= 4) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Limite raggiunto')
                                ->body('Puoi selezionare al massimo 4 servizi in evidenza.')
                                ->send();
                            return false;
                        }
                        $record->update(['featured' => $state]);
                        return $state;
                    }),
            ])
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
