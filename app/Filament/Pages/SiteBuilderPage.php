<?php

namespace App\Filament\Pages;

use App\Models\BusinessPageBlock;
use App\Models\PageTemplate;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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
                \Filament\Tables\Actions\Action::make('edit')
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

            Action::make('changeTemplate')
                ->label('Cambia template')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Select::make('template_id')
                        ->label('Template')
                        ->options(fn () => PageTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->required(),
                    Checkbox::make('confirm')
                        ->label('Confermo: questa azione è irreversibile. Ordine, testi, immagini e varianti dei blocchi saranno reimpostati.')
                        ->accepted()
                        ->validationMessages(['accepted' => 'Devi confermare per procedere.']),
                ])
                ->modalHeading('Cambia template')
                ->modalDescription('Cambiare template reimposterà l\'ordine, i testi, le immagini e le varianti dei blocchi. Questa azione non è reversibile.')
                ->action(function (array $data): void {
                    $businessId = app('current_business_id');
                    $template   = PageTemplate::with('pageTemplateBlocks')->find($data['template_id']);

                    if (! $template) {
                        return;
                    }

                    DB::transaction(function () use ($businessId, $template): void {
                        BusinessPageBlock::withoutGlobalScopes()
                            ->where('business_id', $businessId)
                            ->delete();

                        foreach ($template->pageTemplateBlocks as $templateBlock) {
                            BusinessPageBlock::withoutGlobalScopes()->create([
                                'business_id'            => $businessId,
                                'page_template_id'       => $template->id,
                                'page_template_block_id' => $templateBlock->id,
                                'block_type'             => $templateBlock->block_type,
                                'variant'                => $templateBlock->variant,
                                'sort_order'             => $templateBlock->sort_order,
                                'is_enabled'             => $templateBlock->is_enabled,
                                'is_required'            => $templateBlock->is_required,
                                'is_locked'              => $templateBlock->is_locked,
                                'content'                => $templateBlock->content,
                                'settings'               => $templateBlock->settings,
                                'schema_version'         => $templateBlock->schema_version,
                            ]);
                        }

                        \App\Models\SalonProfile::withoutGlobalScopes()
                            ->where('business_id', $businessId)
                            ->update(['page_template_id' => $template->id]);
                    });

                    Notification::make()
                        ->title('Template applicato. Le modifiche sono visibili subito sul sito.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
