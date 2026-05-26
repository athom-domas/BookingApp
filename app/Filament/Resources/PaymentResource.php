<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Prenotazioni';
    protected static ?int $navigationSort = 3;
    protected static ?string $modelLabel = 'pagamento';
    protected static ?string $pluralModelLabel = 'pagamenti';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('appointment_id')
                    ->label('Prenotazione #')
                    ->sortable()
                    ->url(fn (Payment $record): string => AppointmentResource::getUrl('edit', ['record' => $record->appointment_id])),

                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Importo')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'completed' => 'Completato',
                        'refunded'  => 'Rimborsato',
                        'failed'    => 'Fallito',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'completed' => 'success',
                        'refunded'  => 'info',
                        'failed'    => 'danger',
                        default     => 'secondary',
                    }),

                TextColumn::make('payment_method')
                    ->label('Metodo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'stripe' => 'Stripe',
                        'cash'   => 'Contanti',
                        'pos'    => 'POS',
                        default  => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'info',
                        'cash'   => 'success',
                        'pos'    => 'warning',
                        default  => 'secondary',
                    }),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'completed' => 'Completato',
                        'refunded'  => 'Rimborsato',
                        'failed'    => 'Fallito',
                        'cancelled' => 'Annullato',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('Metodo')
                    ->options([
                        'stripe' => 'Stripe',
                        'cash'   => 'Contanti',
                        'pos'    => 'POS',
                    ]),

                Filter::make('created_at')
                    ->label('Periodo')
                    ->form([
                        DatePicker::make('from')->label('Dal'),
                        DatePicker::make('until')->label('Al'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dal: ' . $data['from'];
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Al: ' . $data['until'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('refund')
                    ->label('Rimborsa')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Conferma rimborso')
                    ->modalDescription('Sei sicuro di voler rimborsare questo pagamento?')
                    ->action(function (Payment $record): void {
                    try {
                        app(PaymentService::class)->refundPayment($record->id);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Rimborso effettuato')
                            ->send();
                    } catch (\Throwable $e) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Rimborso fallito')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                    ->visible(fn (Payment $record): bool => $record->status === 'completed' && $record->payment_method === 'stripe'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
