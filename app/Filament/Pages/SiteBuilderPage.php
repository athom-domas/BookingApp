<?php

namespace App\Filament\Pages;

use App\Models\BusinessPageBlock;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class SiteBuilderPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Il mio sito';
    protected static ?string $title = 'Il mio sito';
    protected string $view = 'filament.pages.site-builder';
    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BusinessPageBlock::withoutGlobalScopes()
                    ->where('business_id', app('current_business_id'))
                    ->orderBy('sort_order')
            )
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('block_type')
                    ->label('Blocco')
                    ->formatStateUsing(function ($state): string {
                        $class = PageBlockRegistry::find($state);
                        return $class ? $class::label() : $state;
                    }),
                TextColumn::make('variant')
                    ->label('Variante')
                    ->badge()
                    ->formatStateUsing(function ($state, $record): string {
                        $class = PageBlockRegistry::find($record->block_type);
                        return $class ? ($class::variants()[$state]['label'] ?? $state) : $state;
                    }),
                ToggleColumn::make('is_enabled')
                    ->label('Visibile')
                    ->disabled(fn (BusinessPageBlock $record): bool => $record->is_required)
                    ->beforeStateUpdated(function (BusinessPageBlock $record, bool $state): void {
                        abort_if($record->is_required, 403, 'Required blocks cannot be disabled.');
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil')
                    ->slideOver()
                    ->visible(fn ($record) => ! $record->is_locked)
                    ->form(function (BusinessPageBlock $record): array {
                        $blockClass = PageBlockRegistry::find($record->block_type);

                        $fields = [
                            Select::make('variant')
                                ->label('Variante layout')
                                ->options(function () use ($blockClass): array {
                                    if (! $blockClass) {
                                        return [];
                                    }
                                    return collect($blockClass::variants())
                                        ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
                                        ->all();
                                })
                                ->required(),
                        ];

                        if ($blockClass) {
                            $blockFields = $blockClass::filamentFields();
                            if (! empty($blockFields)) {
                                $fields[] = Section::make('Contenuto')->schema($blockFields);
                            }
                        }

                        return $fields;
                    })
                    ->fillForm(function (BusinessPageBlock $record): array {
                        return ['variant' => $record->variant, 'content' => $record->content ?? [], 'settings' => $record->settings ?? []];
                    })
                    ->action(function (BusinessPageBlock $record, array $data): void {
                        $blockClass = PageBlockRegistry::find($record->block_type);

                        if ($blockClass) {
                            $validator = validator($data, array_merge(
                                $blockClass::contentRules(),
                                $blockClass::settingsRules(),
                                ['variant' => ['required', 'string']],
                            ));

                            if ($validator->fails()) {
                                Notification::make()
                                    ->title('Dati non validi')
                                    ->body(implode(' ', $validator->errors()->all()))
                                    ->danger()
                                    ->send();
                                return;
                            }
                        }

                        $record->update([
                            'variant'  => $data['variant'],
                            'content'  => $data['content'] ?? $record->content,
                            'settings' => $data['settings'] ?? $record->settings,
                        ]);

                        Notification::make()
                            ->title('Modifiche salvate. Le modifiche sono visibili subito sul sito.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openSite')
                ->label('Apri sito pubblico')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => url('/'))
                ->openUrlInNewTab(),

        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
