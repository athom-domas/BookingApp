<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Models\AvailabilityRule;
use App\Models\SalonProfile;
use App\Models\StaffBlockout;
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

    public array $blockouts = [];
    public ?string $newStart = null;
    public ?string $newEnd = null;
    public ?string $newReason = null;

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

        $this->loadBlockouts();
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

    private static array $dayKeyMap = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat'];

    private function salonRangesForDay(array $openingHours, int $dayOfWeek): ?array
    {
        $day = $openingHours[self::$dayKeyMap[$dayOfWeek]] ?? null;
        $type = $day['type'] ?? 'closed';

        return match ($type) {
            'continuous' => [['start' => $day['open_time'], 'end' => $day['close_time']]],
            'split'      => [
                ['start' => $day['morning_open'],   'end' => $day['morning_close']],
                ['start' => $day['afternoon_open'], 'end' => $day['afternoon_close']],
            ],
            default => null,
        };
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $salonHours = SalonProfile::current()?->opening_hours ?? [];
        $t          = fn(?string $time): string => substr($time ?? '00:00', 0, 5);
        $errors     = [];

        foreach ($data['days'] as $day => $values) {
            if (! ($values['is_available'] ?? false)) {
                continue;
            }

            $ranges = $this->salonRangesForDay($salonHours, (int) $day);
            $label  = self::$dayLabels[$day];

            if ($ranges === null) {
                $errors[] = "{$label}: il salone è chiuso.";
                continue;
            }

            $s1 = $t($values['start_time'] ?? null);
            $e1 = $t($values['end_time']   ?? null);
            if ($s1 && $e1) {
                if ($s1 < $t($ranges[0]['start'])) {
                    $errors[] = "{$label}: inizio mattina {$s1} è prima dell'apertura salone ({$t($ranges[0]['start'])}).";
                }
                if ($e1 > $t($ranges[0]['end'])) {
                    $errors[] = "{$label}: fine mattina {$e1} è dopo la chiusura salone ({$t($ranges[0]['end'])}).";
                }
            }

            $s2 = $t($values['start_time_2'] ?? null);
            $e2 = $t($values['end_time_2']   ?? null);
            if ($s2 && $e2) {
                $r  = $ranges[1] ?? $ranges[0];
                if ($s2 < $t($r['start'])) {
                    $errors[] = "{$label}: inizio pomeriggio {$s2} è prima dell'apertura salone ({$t($r['start'])}).";
                }
                if ($e2 > $t($r['end'])) {
                    $errors[] = "{$label}: fine pomeriggio {$e2} è dopo la chiusura salone ({$t($r['end'])}).";
                }
            }
        }

        if (! empty($errors)) {
            Notification::make()
                ->title('Orari non validi')
                ->body(implode("\n", $errors))
                ->danger()
                ->persistent()
                ->send();
            return;
        }

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

    public function loadBlockouts(): void
    {
        $this->blockouts = StaffBlockout::where('user_id', $this->record->id)
            ->where('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(fn ($b) => [
                'id'         => $b->id,
                'start_date' => $b->start_date->format('d/m/Y'),
                'end_date'   => $b->end_date->format('d/m/Y'),
                'reason'     => $b->reason,
            ])
            ->toArray();
    }

    public function addBlockout(): void
    {
        $this->validate([
            'newStart'  => 'required|date',
            'newEnd'    => 'required|date|after_or_equal:newStart',
            'newReason' => 'nullable|string|max:255',
        ]);

        StaffBlockout::create([
            'user_id'    => $this->record->id,
            'start_date' => $this->newStart,
            'end_date'   => $this->newEnd,
            'reason'     => $this->newReason ?: null,
        ]);

        $this->newStart  = null;
        $this->newEnd    = null;
        $this->newReason = null;

        $this->loadBlockouts();

        Notification::make()->title('Periodo aggiunto')->success()->send();
    }

    public function deleteBlockout(int $id): void
    {
        StaffBlockout::where('user_id', $this->record->id)->where('id', $id)->delete();
        $this->loadBlockouts();

        Notification::make()->title('Periodo rimosso')->success()->send();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return "Disponibilità — {$this->record->name}";
    }
}
