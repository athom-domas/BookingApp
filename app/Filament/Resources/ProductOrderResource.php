<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductOrderResource\Pages;
use App\Models\ProductOrder;
use App\Services\ProductOrderService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductOrderResource extends Resource
{
    protected static ?string $model = ProductOrder::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 6;
    protected static ?string $modelLabel = 'ordine prodotti';
    protected static ?string $pluralModelLabel = 'ordini prodotti';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() || ! auth()->user()?->isStaff();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Riepilogo ordine')
                ->schema([
                    TextEntry::make('user.name')->label('Cliente'),
                    TextEntry::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
                    TextEntry::make('status')->label('Stato')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending'   => 'In attesa di pagamento',
                            'confirmed' => 'Confermato',
                            'ready'     => 'Pronto per il ritiro',
                            'completed' => 'Completato',
                            'cancelled' => 'Cancellato',
                            default     => $state,
                        })
                        ->color(fn (string $state): string => match ($state) {
                            'pending'   => 'warning',
                            'confirmed' => 'info',
                            'ready'     => 'success',
                            'completed' => 'gray',
                            'cancelled' => 'danger',
                            default     => 'gray',
                        }),
                    TextEntry::make('payment_method')->label('Metodo pagamento')
                        ->formatStateUsing(fn ($state) => $state === 'stripe' ? 'Online (Stripe)' : 'In salone (contanti)'),
                    TextEntry::make('payment_status')->label('Stato pagamento')
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending'   => 'In attesa',
                            'paid'      => 'Pagato',
                            'cancelled' => 'Cancellato',
                            'refunded'  => 'Rimborsato',
                            default     => $state,
                        }),
                    TextEntry::make('notes')->label('Note')->placeholder('—'),
                ])
                ->columns(2),

            Section::make('Articoli')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            TextEntry::make('product.name')->label('Prodotto'),
                            TextEntry::make('quantity')->label('Quantità'),
                            TextEntry::make('unit_price')->label('Prezzo unitario')->money('EUR'),
                            TextEntry::make('subtotal')->label('Subtotale')->money('EUR'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Cliente')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('status')->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'ready'     => 'Pronto',
                        'completed' => 'Completato',
                        'cancelled' => 'Cancellato',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'info',
                        'ready'     => 'success',
                        'completed' => 'gray',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('payment_method')->label('Pagamento')
                    ->formatStateUsing(fn ($state) => $state === 'stripe' ? 'Stripe' : 'Contanti'),
                TextColumn::make('total')
                    ->label('Totale')
                    ->getStateUsing(fn (ProductOrder $record): string => number_format($record->load('items')->total, 2, ',', '.') . ' €')
                    ->sortable(false),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'ready'     => 'Pronto',
                        'completed' => 'Completato',
                        'cancelled' => 'Cancellato',
                    ]),
            ])
            ->actions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductOrders::route('/'),
            'view'  => Pages\ViewProductOrder::route('/{record}'),
        ];
    }
}
