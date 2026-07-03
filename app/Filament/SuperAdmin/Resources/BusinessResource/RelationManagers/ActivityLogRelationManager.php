<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'error'    => 'danger',
                        'activity' => 'primary',
                        'system'   => 'gray',
                        default    => 'gray',
                    }),

                TextColumn::make('level')
                    ->label('Livello')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'critical' => 'danger',
                        'error'    => 'warning',
                        'warning'  => 'warning',
                        'info'     => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(80)
                    ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState() ?? '') > 80 ? $column->getState() : null),

                TextColumn::make('source')
                    ->label('Sorgente')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subject_type')
                    ->label('Modello')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                TextColumn::make('causer.name')
                    ->label('Utente')
                    ->placeholder('—'),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('method')
                    ->label('Metodo')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'activity' => 'Attività',
                        'error'    => 'Errore',
                        'system'   => 'Sistema',
                    ]),

                SelectFilter::make('level')
                    ->label('Livello')
                    ->options([
                        'info'     => 'Info',
                        'warning'  => 'Warning',
                        'error'    => 'Error',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('Modello')
                    ->options([
                        'App\\Models\\Appointment'  => 'Appuntamento',
                        'App\\Models\\Payment'      => 'Pagamento',
                        'App\\Models\\ProductOrder' => 'Ordine prodotto',
                        'App\\Models\\Service'      => 'Servizio',
                    ]),

                Filter::make('date_from')
                    ->label('Da data')
                    ->form([\Filament\Forms\Components\DatePicker::make('date_from')->label('Da')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['date_from'] ?? null,
                        fn ($q) => $q->whereDate('created_at', '>=', $data['date_from'] ?? null)
                    )),

                Filter::make('date_to')
                    ->label('A data')
                    ->form([\Filament\Forms\Components\DatePicker::make('date_to')->label('A')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['date_to'] ?? null,
                        fn ($q) => $q->whereDate('created_at', '<=', $data['date_to'] ?? null)
                    )),
            ])
            ->recordAction(null)
            ->paginated([25, 50, 100]);
    }
}
