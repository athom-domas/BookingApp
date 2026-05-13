<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointmentsAsCustomer';

    protected static ?string $title = 'Appuntamenti';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_date')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('service.name')
                    ->label('Servizio')
                    ->sortable(),

                TextColumn::make('staff.name')
                    ->label('Staff')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'gray',
                        default => 'secondary',
                    }),

                TextColumn::make('final_price')
                    ->label('Prezzo finale')
                    ->money('EUR')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('payment.status')
                    ->label('Pagamento')
                    ->badge()
                    ->placeholder('Nessuno')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'In attesa',
                        'completed' => 'Completato',
                        'refunded' => 'Rimborsato',
                        'failed' => 'Fallito',
                        'cancelled' => 'Annullato',
                        default => $state ?? 'Nessuno',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'refunded' => 'info',
                        'failed', 'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('notes')
                    ->label('Note appuntamento')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->defaultSort('scheduled_date', 'desc')
            ->actions([
                Action::make('open')
                    ->label('Apri')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Appointment $record): string => AppointmentResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
