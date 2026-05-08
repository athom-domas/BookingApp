<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AvailabilityRuleResource\Pages;
use App\Models\AvailabilityRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class AvailabilityRuleResource extends Resource
{
    protected static ?string $model = AvailabilityRule::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $modelLabel = 'regola disponibilità';
    protected static ?string $pluralModelLabel = 'regole disponibilità';

    private static array $dayLabels = [
        0 => 'Domenica',
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('Staff')
                ->relationship('user', 'name')
                ->required()
                ->searchable()
                ->live(),

            Select::make('day_of_week')
                ->label('Giorno')
                ->options(self::$dayLabels)
                ->required()
                ->unique(
                    table: AvailabilityRule::class,
                    column: 'day_of_week',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('user_id', $get('user_id')),
                ),

            TimePicker::make('start_time')
                ->label('Inizio')
                ->required(),

            TimePicker::make('end_time')
                ->label('Fine')
                ->required(),

            Toggle::make('is_available')
                ->label('Disponibile')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('day_of_week')
                    ->label('Giorno')
                    ->formatStateUsing(fn (int $state): string => self::$dayLabels[$state] ?? (string) $state)
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Inizio'),

                TextColumn::make('end_time')
                    ->label('Fine'),

                ToggleColumn::make('is_available')
                    ->label('Disponibile'),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('Staff')
                    ->relationship('user', 'name')
                    ->searchable(),

                SelectFilter::make('day_of_week')
                    ->label('Giorno')
                    ->options(self::$dayLabels),

                TernaryFilter::make('is_available')
                    ->label('Disponibile')
                    ->boolean()
                    ->trueLabel('Disponibile')
                    ->falseLabel('Non disponibile'),
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
            'index'  => Pages\ListAvailabilityRules::route('/'),
            'create' => Pages\CreateAvailabilityRule::route('/create'),
            'edit'   => Pages\EditAvailabilityRule::route('/{record}/edit'),
        ];
    }
}
