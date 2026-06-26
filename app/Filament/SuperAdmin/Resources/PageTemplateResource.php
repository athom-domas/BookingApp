<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;
use App\Filament\SuperAdmin\Resources\PageTemplateResource\RelationManagers;
use App\Models\PageTemplate;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageTemplateResource extends Resource
{
    protected static ?string $model = PageTemplate::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Template sito';
    protected static string|\UnitEnum|null $navigationGroup = 'Piattaforma';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(PageTemplate::class, 'slug', ignoreRecord: true)
                    ->maxLength(80),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->rows(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->default(true),
                Toggle::make('is_default')
                    ->label('Template di default')
                    ->helperText('Un solo template può essere il default. Gli altri verranno resettati.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug'),
                IconColumn::make('is_active')->label('Attivo')->boolean(),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('pageTemplateBlocks_count')
                    ->counts('pageTemplateBlocks')
                    ->label('Blocchi'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('clone')
                    ->label('Clona')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->action(function (PageTemplate $record): void {
                        $clone = $record->replicate(['is_default']);
                        $clone->name = $record->name . ' copia';
                        $clone->slug = Str::slug($record->name . '-copia-' . now()->timestamp);
                        $clone->is_active = false;
                        $clone->is_default = false;
                        $clone->save();

                        foreach ($record->pageTemplateBlocks as $block) {
                            $clone->pageTemplateBlocks()->create($block->only([
                                'block_type', 'variant', 'sort_order', 'is_enabled',
                                'is_required', 'is_locked', 'content', 'settings', 'schema_version',
                            ]));
                        }
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\PageTemplateBlocksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPageTemplates::route('/'),
            'create' => Pages\CreatePageTemplate::route('/create'),
            'edit'   => Pages\EditPageTemplate::route('/{record}/edit'),
        ];
    }
}
