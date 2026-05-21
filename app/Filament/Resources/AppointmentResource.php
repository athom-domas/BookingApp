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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Filament\Tables\Table;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $modelLabel = 'prenotazione';
    protected static ?string $pluralModelLabel = 'prenotazioni';

    public static function canEdit($record): bool
    {
        if (auth()->user()?->isStaff()) {
            return $record->staff_id === auth()->id()
                && ! in_array($record->status, ['completed', 'cancelled']);
        }

        return ! in_array($record->status, ['completed', 'cancelled']);
    }

    public static function canDelete($record): bool
    {
        return ! auth()->user()?->isStaff();
    }

    public static function canCreate(): bool
    {
        return ! auth()->user()?->isStaff();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('Cliente')
                ->relationship('user', 'name', fn ($query) => $query->role('customer'))
                ->required()
                ->searchable()
                ->disabled(fn ($record) => $record !== null),

            Select::make('service_ids')
                ->label('Servizi')
                ->options(fn() => Service::active()->orderBy('name')->pluck('name', 'id')->all())
                ->multiple()
                ->searchable()
                ->required()
                ->disabled(fn ($record) => $record !== null),

            Select::make('staff_id')
                ->label('Staff')
                ->relationship('staff', 'name', fn ($query) => $query->role('staff'))
                ->required()
                ->searchable()
                ->disabled(fn ($record) => auth()->user()?->isStaff() || in_array($record?->status, ['completed', 'cancelled'])),

            DateTimePicker::make('scheduled_date')
                ->label('Data e ora')
                ->required()
                ->disabled(fn ($record) => $record !== null),

            Select::make('status')
                ->label('Stato')
                ->options([
                    'pending'   => 'In attesa',
                    'confirmed' => 'Confermato',
                    'cancelled' => 'Annullato',
                    'completed' => 'Completato',
                ])
                ->required()
                ->live()
                ->default('pending')
                ->disabled(fn ($record) => in_array($record?->status, ['completed', 'cancelled'])),

            Textarea::make('notes')
                ->label('Note')
                ->rows(3)
                ->columnSpanFull()
                ->disabled(fn ($record) => $record !== null),

            Hidden::make('has_completed_payment')
                ->dehydrated(false),

            Select::make('payment_method')
                ->label('Metodo di pagamento')
                ->options(['cash' => 'Contanti', 'pos' => 'POS (carta)'])
                ->required()
                ->disabled(fn (Get $get) => (bool) $get('has_completed_payment'))
                ->hidden(fn (Get $get, string $operation) =>
                    $operation !== 'edit'
                    || $get('status') !== 'completed'
                ),

            TextInput::make('payment_amount')
                ->label('Importo (€)')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->disabled(fn (Get $get) => (bool) $get('has_completed_payment'))
                ->hidden(fn (Get $get, string $operation) =>
                    $operation !== 'edit'
                    || $get('status') !== 'completed'
                ),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isStaff = auth()->user()?->isStaff();

        return $table
            ->modifyQueryUsing(fn (Builder $query) =>
                $isStaff ? $query->where('staff_id', auth()->id()) : $query
            )
            ->defaultSort('scheduled_date', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('services_label')
                    ->label('Servizi')
                    ->getStateUsing(fn($record) => $record->services_label)
                    ->wrap(),

                TextColumn::make('scheduled_date')
                    ->label('Data e ora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                        default     => $state,
                    })
                    ->color(fn(string $state): string => match ($state) {
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
                    ->relationship('staff', 'name', fn ($query) => $query->role('staff'))
                    ->searchable()
                    ->hidden($isStaff),

                Filter::make('scheduled_date')
                    ->label('Data')
                    ->form([
                        DatePicker::make('from')->label('Dal'),
                        DatePicker::make('until')->label('Al'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn(Builder $q, $date) => $q->whereDate('scheduled_date', '>=', $date))
                            ->when($data['until'], fn(Builder $q, $date) => $q->whereDate('scheduled_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from']) {
                            $indicators[] = 'Dal: ' . Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until']) {
                            $indicators[] = 'Al: ' . Carbon::parse($data['until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
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
                    ->fillForm(fn(Appointment $record): array => [
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
                    ->visible(fn(Appointment $record): bool => ! in_array($record->status, ['pending', 'completed', 'cancelled']) && (! $record->payment || $record->payment->status !== 'completed')),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make()->hidden($isStaff),
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
