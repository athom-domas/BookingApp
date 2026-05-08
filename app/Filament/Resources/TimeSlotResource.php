<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeSlotResource\Pages;
use App\Models\TimeSlot;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimeSlotResource extends Resource
{
    protected static ?string $model = TimeSlot::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $modelLabel = 'slot';
    protected static ?string $pluralModelLabel = 'slot';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label('Inizio'),

                TextColumn::make('end_time')
                    ->label('Fine'),

                TextColumn::make('is_available')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Disponibile' : 'Occupato')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label('Staff')
                    ->relationship('user', 'name')
                    ->searchable(),

                Filter::make('date')
                    ->label('Data')
                    ->form([
                        DatePicker::make('date')->label('Data'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder =>
                        $query->when($data['date'], fn (Builder $q) => $q->whereDate('date', $data['date']))
                    ),

                TernaryFilter::make('is_available')
                    ->label('Disponibile')
                    ->boolean()
                    ->trueLabel('Disponibili')
                    ->falseLabel('Occupati'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeSlots::route('/'),
        ];
    }
}
