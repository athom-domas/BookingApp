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
    protected static string|\UnitEnum|null $navigationGroup = 'Prenotazioni';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'prenotazione';
    protected static ?string $pluralModelLabel = 'prenotazioni';

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if ($user?->isAdmin()) {
            return true;
        }

        if ($user?->isStaff() && $user->can('appointments.edit')) {
            $owned  = $record->staff_id === $user->id;
            $canAny = $user->can('appointments.view_all');
            return ($owned || $canAny)
                && ! in_array($record->status, ['completed', 'cancelled']);
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if ($user?->isAdmin()) {
            return true;
        }
        if ($user?->isStaff() && $user->can('appointments.delete')) {
            return $record->staff_id === $user->id || $user->can('appointments.view_all');
        }
        return false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('appointments.create'))) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('user_id')
                ->label('Cliente')
                ->relationship('user', 'name', fn($query) => $query->role('customer'))
                ->required()
                ->searchable()
                ->disabled(fn($record) => $record !== null),

            Select::make('service_ids')
                ->label('Servizi')
                ->options(fn() => Service::active()->orderBy('name')->pluck('name', 'id')->all())
                ->multiple()
                ->searchable()
                ->required()
                ->disabled(fn($record) => $record !== null),

            Select::make('staff_id')
                ->label('Staff')
                ->relationship('staff', 'name', fn($query) => $query->role('staff'))
                ->required()
                ->searchable()
                ->default(fn() => auth()->user()?->isStaff() ? auth()->id() : null)
                ->hidden(fn($record) => auth()->user()?->isStaff() && $record === null)
                ->disabled(
                    fn($record) =>
                    $record?->status === 'completed'
                        || $record?->status === 'cancelled'
                        || (! auth()->user()?->isAdmin() && $record !== null)
                ),

            DateTimePicker::make('scheduled_date')
                ->label('Data e ora')
                ->required()
                ->disabled(
                    fn($record) =>
                    $record?->status === 'completed'
                        || (! auth()->user()?->isAdmin()
                            && $record !== null
                            && ! auth()->user()?->can('appointments.edit'))
                ),

            Textarea::make('notes')
                ->label('Note')
                ->rows(3)
                ->columnSpanFull()
                ->disabled(
                    fn($record) =>
                    $record?->status === 'completed'
                        || (! auth()->user()?->isAdmin()
                            && $record !== null
                            && ! auth()->user()?->can('appointments.edit'))
                ),

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
                ->disabled(
                    fn($record) => ($record?->status === 'completed' && $record?->payment?->status !== 'refunded')
                        || (! auth()->user()?->isAdmin() && $record?->status === 'cancelled')
                ),

            Hidden::make('has_completed_payment')
                ->dehydrated(false),

            Select::make('payment_method')
                ->label('Metodo di pagamento')
                ->options(['cash' => 'Contanti', 'pos' => 'POS (carta)'])
                ->required()
                ->hidden(
                    fn(Get $get, string $operation) =>
                    $operation !== 'edit'
                        || $get('status') !== 'completed'
                        || (bool) $get('has_completed_payment')
                ),

            TextInput::make('payment_amount')
                ->label('Importo (€)')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->hidden(
                    fn(Get $get, string $operation) =>
                    $operation !== 'edit'
                        || $get('status') !== 'completed'
                        || (bool) $get('has_completed_payment')
                ),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user       = auth()->user();
        $isStaff    = $user?->isStaff() ?? false;
        $hasViewAll = $isStaff && ($user?->can('appointments.view_all') ?? false);
        $canDelete  = ! $isStaff || ($user?->can('appointments.delete') ?? false);

        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => ($isStaff && ! $hasViewAll) ? $query->where('staff_id', $user->id) : $query
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
                    ->relationship('staff', 'name', fn($query) => $query->role('staff'))
                    ->searchable()
                    ->hidden($isStaff && ! $hasViewAll),

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
                    ->visible(fn(Appointment $record): bool =>
                        ! in_array($record->status, ['pending', 'completed', 'cancelled'])
                        && (! $record->payment || $record->payment->status !== 'completed')
                        && (auth()->user()?->isAdmin() || auth()->user()?->can('appointments.payments'))
                    ),
                EditAction::make()
                    ->hidden(fn(Appointment $record) =>
                        ! auth()->user()?->isAdmin()
                        && (! auth()->user()?->can('appointments.edit') || in_array($record->status, ['completed', 'cancelled']))
                    ),
                DeleteAction::make()
                    ->hidden(fn() => auth()->user()?->isStaff() && ! auth()->user()?->can('appointments.delete')),
            ])
            ->bulkActions([
                DeleteBulkAction::make()->hidden(! $canDelete),
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
