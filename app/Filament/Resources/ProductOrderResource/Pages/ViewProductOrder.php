<?php

namespace App\Filament\Resources\ProductOrderResource\Pages;

use App\Filament\Resources\ProductOrderResource;
use App\Services\ProductOrderService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProductOrder extends ViewRecord
{
    protected static string $resource = ProductOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('advance')
                ->label(fn () => match ($this->record->status) {
                    'confirmed' => 'Segna come pronto',
                    'ready'     => 'Segna come completato',
                    default     => 'Avanza stato',
                })
                ->icon('heroicon-o-arrow-right-circle')
                ->visible(fn () => in_array($this->record->status, ['confirmed', 'ready']))
                ->action(function () {
                    $next = match ($this->record->status) {
                        'confirmed' => 'ready',
                        'ready'     => 'completed',
                        default     => null,
                    };
                    if ($next) {
                        $this->record->update(['status' => $next]);
                        Notification::make()->success()->title('Stato aggiornato')->send();
                        $this->refreshFormData(['status']);
                    }
                }),

            Action::make('cancel')
                ->label('Cancella ordine')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->isCancellable())
                ->requiresConfirmation()
                ->action(function () {
                    app(ProductOrderService::class)->cancelOrder($this->record);
                    Notification::make()->success()->title('Ordine cancellato')->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
