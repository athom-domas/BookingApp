<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Pagamenti';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('appointment_id')
                    ->label('Prenotazione #')
                    ->sortable(),

                TextColumn::make('appointment.service.name')
                    ->label('Servizio')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('appointment.scheduled_date')
                    ->label('Data appuntamento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'In attesa',
                        'completed' => 'Completato',
                        'refunded' => 'Rimborsato',
                        'failed' => 'Fallito',
                        'cancelled' => 'Annullato',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'refunded' => 'info',
                        'failed', 'cancelled' => 'danger',
                        default => 'secondary',
                    }),

                TextColumn::make('stripe_transaction_id')
                    ->label('Stripe ID')
                    ->copyable()
                    ->limit(28)
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('markRefunded')
                    ->label('Segna rimborsato')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record): bool => $record->status === 'completed')
                    ->action(fn (Payment $record): bool => $record->update(['status' => 'refunded'])),
            ]);
    }
}
