<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Models\AvailabilityRule;
use App\Models\User;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ManageAvailability extends Page
{
    protected static string $resource = StaffResource::class;

    protected string $view = 'filament.resources.staff-resource.pages.manage-availability';

    public User $record;

    public ?array $data = [];

    private static array $dayOrder = [1, 2, 3, 4, 5, 6, 0];

    private static array $dayLabels = [
        1 => 'Lunedì',
        2 => 'Martedì',
        3 => 'Mercoledì',
        4 => 'Giovedì',
        5 => 'Venerdì',
        6 => 'Sabato',
        0 => 'Domenica',
    ];

    public function mount(User $record): void
    {
        $this->record = $record;

        $rules = AvailabilityRule::where('user_id', $record->id)
            ->get()
            ->keyBy('day_of_week');

        $days = [];
        foreach (self::$dayOrder as $day) {
            $rule = $rules->get($day);
            $days[$day] = [
                'is_available' => $rule?->is_available ?? false,
                'start_time'   => $rule?->start_time,
                'end_time'     => $rule?->end_time,
                'start_time_2' => $rule?->start_time_2,
                'end_time_2'   => $rule?->end_time_2,
            ];
        }

        $this->form->fill(['days' => $days]);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (self::$dayOrder as $day) {
            $label = self::$dayLabels[$day];

            $sections[] = Section::make($label)
                ->columns(4)
                ->schema([
                    Toggle::make("days.{$day}.is_available")
                        ->label('Disponibile')
                        ->live()
                        ->columnSpanFull(),

                    TimePicker::make("days.{$day}.start_time")
                        ->label('Inizio mattina')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => (bool) $get("days.{$day}.is_available")),

                    TimePicker::make("days.{$day}.end_time")
                        ->label('Fine mattina')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => (bool) $get("days.{$day}.is_available")),

                    TimePicker::make("days.{$day}.start_time_2")
                        ->label('Inizio pomeriggio')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => filled($get("days.{$day}.end_time_2"))),

                    TimePicker::make("days.{$day}.end_time_2")
                        ->label('Fine pomeriggio')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => filled($get("days.{$day}.start_time_2"))),
                ]);
        }

        return $schema->schema($sections)->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data['days'] as $day => $values) {
            $isAvailable = (bool) ($values['is_available'] ?? false);

            AvailabilityRule::updateOrCreate(
                ['user_id' => $this->record->id, 'day_of_week' => $day],
                [
                    'is_available'  => $isAvailable,
                    'start_time'    => $isAvailable ? ($values['start_time'] ?? null) : null,
                    'end_time'      => $isAvailable ? ($values['end_time'] ?? null) : null,
                    'start_time_2'  => $isAvailable ? ($values['start_time_2'] ?? null) : null,
                    'end_time_2'    => $isAvailable ? ($values['end_time_2'] ?? null) : null,
                ]
            );
        }

        Notification::make()
            ->title('Disponibilità salvata')
            ->success()
            ->send();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "Disponibilità — {$this->record->name}";
    }
}
