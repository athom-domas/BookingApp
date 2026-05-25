<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestAppointmentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Ultimi appuntamenti';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->with(['user', 'staff'])
                    ->where('scheduled_date', '>=', now())
                    ->oldest('scheduled_date')
                    ->limit(5)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente'),

                TextColumn::make('staff.name')
                    ->label('Staff'),

                TextColumn::make('services_label')
                    ->label('Servizi')
                    ->getStateUsing(fn ($record) => $record->services_label),

                TextColumn::make('scheduled_date')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i'),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'gray',
                        default     => 'secondary',
                    }),
            ]);
    }
}
