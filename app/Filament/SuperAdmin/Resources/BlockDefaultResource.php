<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\BlockDefaultResource\Pages;
use App\Models\BlockDefault;
use App\PageBlocks\PageBlockRegistry;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlockDefaultResource extends Resource
{
    protected static ?string $model = BlockDefault::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Blocchi sito';
    protected static string|\UnitEnum|null $navigationGroup = 'Piattaforma';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool { return false; }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Select::make('variant')
                    ->label('Variante default')
                    ->options(function ($record) {
                        if (! $record) return [];
                        $blockClass = PageBlockRegistry::find($record->block_type);
                        if (! $blockClass) return [];
                        return collect($blockClass::variants())
                            ->mapWithKeys(fn ($v, $k) => [$k => $v['label']])
                            ->all();
                    })
                    ->required(),
                Toggle::make('is_required')
                    ->label('Obbligatorio')
                    ->helperText('Il salone non può disabilitarlo.'),
                Toggle::make('is_enabled')
                    ->label('Abilitato di default')
                    ->helperText('Stato iniziale per i nuovi saloni.'),
                Toggle::make('is_locked')
                    ->label('Bloccato')
                    ->helperText('Il salone non può modificarne il contenuto.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
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
                    ->formatStateUsing(function ($state, BlockDefault $record): string {
                        $class = PageBlockRegistry::find($record->block_type);
                        return $class ? ($class::variants()[$state]['label'] ?? $state) : $state;
                    }),
                IconColumn::make('is_required')->label('Obbligatorio')->boolean(),
                IconColumn::make('is_enabled')->label('Abilitato')->boolean(),
                IconColumn::make('is_locked')->label('Bloccato')->boolean(),
            ])
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlockDefaults::route('/'),
            'edit'  => Pages\EditBlockDefault::route('/{record}/edit'),
        ];
    }
}
