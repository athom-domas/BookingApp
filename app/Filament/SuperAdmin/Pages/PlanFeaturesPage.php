<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlanFeature;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PlanFeaturesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Feature dei piani';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'piani-feature';

    protected string $view = 'filament.pages.plan-features';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        $costSensitive = ['whatsapp_ai', 'whatsapp_notifications'];

        return $table
            ->query(PlanFeature::query()->orderBy('key'))
            ->columns([
                TextColumn::make('label')
                    ->label('Feature')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descrizione')
                    ->wrap(),

                TextColumn::make('min_plan')
                    ->label('Piano minimo')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'base'  => 'success',
                        'plus'  => 'primary',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'base'  => 'Base',
                        'plus'  => 'Plus',
                        default => 'Disabilitata',
                    }),
            ])
            ->actions([
                TableAction::make('edit_min_plan')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil')
                    ->requiresConfirmation(fn (PlanFeature $record) => in_array($record->key, $costSensitive))
                    ->modalHeading(fn (PlanFeature $record) => in_array($record->key, $costSensitive)
                        ? 'Attenzione: feature con costo variabile'
                        : 'Modifica piano minimo')
                    ->modalDescription(fn (PlanFeature $record) => in_array($record->key, $costSensitive)
                        ? 'Questa feature genera costi (AI/WhatsApp). Assicurati di volerla rendere disponibile nel piano selezionato.'
                        : null)
                    ->form([
                        Select::make('min_plan')
                            ->label('Piano minimo')
                            ->options([
                                'base' => 'Base (tutti i piani)',
                                'plus' => 'Plus (solo piano Plus)',
                                ''     => 'Disabilitata',
                            ])
                            ->required(false),
                    ])
                    ->fillForm(fn (PlanFeature $record) => ['min_plan' => $record->min_plan ?? ''])
                    ->action(function (PlanFeature $record, array $data): void {
                        $record->update(['min_plan' => $data['min_plan'] === '' ? null : $data['min_plan']]);

                        Notification::make()
                            ->title("Feature '{$record->label}' aggiornata.")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }
}
