<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\ActivityLog;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlatformLogsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Platform Logs';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.platform-logs';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('business.name')
                    ->label('Salone')
                    ->placeholder('Piattaforma')
                    ->searchable(),

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
                    ->limit(100)
                    ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState() ?? '') > 100 ? $column->getState() : null),

                TextColumn::make('source')
                    ->label('Sorgente')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->placeholder('—'),

                TextColumn::make('method')
                    ->label('Metodo')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('causer.name')
                    ->label('Utente')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label('Livello')
                    ->options([
                        'info'     => 'Info',
                        'warning'  => 'Warning',
                        'error'    => 'Error',
                        'critical' => 'Critical',
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
