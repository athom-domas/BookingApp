<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\ActivityLog;
use App\Models\Business;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
                    ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState() ?? '') > 100 ? $column->getState() : null)
                    ->copyable()
                    ->copyableState(fn ($record): string => $record->description ?? '')
                    ->copyMessage('Copiato!')
                    ->copyMessageDuration(1500),

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
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'activity' => 'Activity',
                        'error'    => 'Error',
                        'system'   => 'System',
                    ]),

                SelectFilter::make('level')
                    ->label('Livello')
                    ->options([
                        'info'     => 'Info',
                        'warning'  => 'Warning',
                        'error'    => 'Error',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('business_id')
                    ->label('Salone')
                    ->options(fn () => Business::withoutGlobalScopes()->pluck('name', 'id')->all())
                    ->placeholder('Tutti (inclusa piattaforma)')
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $v) => $q->where('business_id', $v)
                    )),

                Filter::make('date_range')
                    ->label('Periodo')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date_from')->label('Da'),
                        \Filament\Forms\Components\DatePicker::make('date_to')->label('A'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                        ->when($data['date_to'] ?? null,   fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
                    ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Elimina selezionati')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Elimina log selezionati')
                        ->modalDescription('Questa operazione è irreversibile. Vuoi procedere?')
                        ->modalSubmitActionLabel('Elimina')
                        ->action(fn (Collection $records) => $records->each->delete())
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->recordAction(null)
            ->paginated([25, 50, 100]);
    }
}
