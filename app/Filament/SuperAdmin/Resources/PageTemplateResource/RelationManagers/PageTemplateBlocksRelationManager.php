<?php

namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\RelationManagers;

use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PageTemplateBlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'pageTemplateBlocks';

    protected static ?string $title = 'Blocchi';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('block_type')
                ->label('Tipo blocco')
                ->options(collect(PageBlockRegistry::all())
                    ->mapWithKeys(fn ($class, $type) => [$type => $class::label()]))
                ->required()
                ->live()
                ->afterStateUpdated(fn ($set, $state) => $set('variant', $state ? PageBlockRegistry::defaultVariant($state) : null)),
            Select::make('variant')
                ->label('Variante')
                ->options(fn (Get $get) => $get('block_type')
                    ? collect(PageBlockRegistry::find($get('block_type'))::variants())
                        ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
                    : [])
                ->required()
                ->live(),
            Toggle::make('is_enabled')->label('Abilitato')->default(true),
            Toggle::make('is_required')->label('Obbligatorio (non disabilitabile dal salone)'),
            Toggle::make('is_locked')->label('Bloccato (non modificabile dal salone)'),
            Section::make('Contenuto e impostazioni')
                ->schema(fn (Get $get): array => $get('block_type') && PageBlockRegistry::find($get('block_type'))
                    ? PageBlockRegistry::find($get('block_type'))::filamentFields()
                    : [])
                ->visible(fn (Get $get): bool => filled($get('block_type'))),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('block_type')->label('Tipo'),
                TextColumn::make('variant')->label('Variante')->badge(),
                IconColumn::make('is_enabled')->label('Attivo')->boolean(),
                IconColumn::make('is_required')->label('Obbligatorio')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->slideOver(),
            ])
            ->actions([
                EditAction::make()->slideOver(),
                DeleteAction::make()
                    ->before(fn (Model $record) => abort_if(
                        $record->is_required,
                        403,
                        'I blocchi obbligatori non possono essere rimossi.'
                    )),
            ]);
    }
}
