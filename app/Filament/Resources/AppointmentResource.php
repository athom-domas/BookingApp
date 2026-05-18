<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppointmentResource\Pages;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $modelLabel = 'prenotazione';
    protected static ?string $pluralModelLabel = 'prenotazioni';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('Cliente')
                ->relationship('user', 'name')
                ->required()
                ->searchable(),

            CheckboxList::make('service_ids')
                ->label('Servizi')
                ->options(fn () => Service::active()->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->columns(2),

            Select::make('staff_id')
                ->label('Staff')
                ->relationship('staff', 'name')
                ->required()
                ->searchable(),

            DateTimePicker::make('scheduled_date')
                ->label('Data e ora')
                ->required(),

            Select::make('status')
                ->label('Stato')
                ->options([
                    'pending'   => 'In attesa',
                    'confirmed' => 'Confermato',
                    'cancelled' => 'Annullato',
                    'completed' => 'Completato',
                ])
                ->required()
                ->default('pending'),

            Textarea::make('notes')
                ->label('Note')
                ->rows(3)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('services_label')
                    ->label('Servizi')
                    ->getStateUsing(fn ($record) => $record->services_label)
                    ->wrap(),

                TextColumn::make('scheduled_date')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

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
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                    ]),

                SelectFilter::make('staff')
                    ->label('Staff')
                    ->relationship('staff', 'name')
                    ->searchable(),
            ])
            ->actions([
                Action::make('register_payment')
                    ->label('Registra pagamento')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->form([
                        Select::make('method')
                            ->label('Metodo di pagamento')
                            ->options([
                                'cash' => 'Contanti',
                                'pos'  => 'POS (carta)',
                            ])
                            ->required(),
                        TextInput::make('amount')
                            ->label('Importo (€)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                    ])
                    ->fillForm(fn (Appointment $record): array => [
                        'amount' => $record->final_price,
                    ])
                    ->action(function (Appointment $record, array $data): void {
                        try {
                            app(PaymentService::class)->recordInPersonPayment(
                                $record->id,
                                $data['method'],
                                (float) $data['amount']
                            );
                        } catch (\App\Exceptions\BookingException $e) {
                            Notification::make()
                                ->title($e->getMessage())
                                ->danger()
                                ->send();

                            $this->halt();
                        }
                    })
                    ->successNotificationTitle('Pagamento registrato con successo')
                    ->visible(fn (Appointment $record): bool => ! $record->payment || $record->payment->status !== 'completed'),
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
            'index'  => Pages\ListAppointments::route('/'),
            'create' => Pages\CreateAppointment::route('/create'),
            'edit'   => Pages\EditAppointment::route('/{record}/edit'),
        ];
    }
}
