<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WaitlistEntryResource\Pages;
use App\Models\Service;
use App\Models\WaitlistEntry;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Lista d\'attesa';

    protected static string|\UnitEnum|null $navigationGroup = 'Prenotazioni';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('service_ids')
                    ->label('Servizi')
                    ->formatStateUsing(
                        fn ($state) => Service::whereIn('id', $state ?? [])->pluck('name')->implode(', ')
                    ),
                TextColumn::make('preferredStaff.name')
                    ->label('Operatore')
                    ->default('Qualsiasi'),
                TextColumn::make('preferred_date_from')
                    ->label('Dal')
                    ->date('d/m/Y'),
                TextColumn::make('preferred_date_to')
                    ->label('Al')
                    ->date('d/m/Y'),
                TextColumn::make('preferred_time_from')
                    ->label('Dalle')
                    ->formatStateUsing(fn ($state) => $state ? substr((string) $state, 0, 5) : '-'),
                TextColumn::make('preferred_time_to')
                    ->label('Alle')
                    ->formatStateUsing(fn ($state) => $state ? substr((string) $state, 0, 5) : '-'),
                TextColumn::make('preferred_days')
                    ->label('Giorni')
                    ->formatStateUsing(
                        fn ($state) => collect($state ?? [])->map(fn ($d) => ucfirst($d))->implode(', ')
                    ),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'waiting'  => 'warning',
                        'notified' => 'info',
                        'booked'   => 'success',
                        default    => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->label('Iscritto il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'waiting'   => 'In attesa',
                        'notified'  => 'Notificato',
                        'booked'    => 'Prenotato',
                        'expired'   => 'Scaduto',
                        'cancelled' => 'Cancellato',
                    ]),
                SelectFilter::make('service')
                    ->label('Servizio')
                    ->options(fn () => Service::pluck('name', 'id')->toArray())
                    ->query(function ($query, $data) {
                        if ($data['value']) {
                            $query->whereJsonContains('service_ids', (int) $data['value']);
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWaitlistEntries::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isStaff()) ?? false;
    }
}
