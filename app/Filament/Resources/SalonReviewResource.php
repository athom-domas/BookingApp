<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalonReviewResource\Pages;
use App\Models\SalonReview;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalonReviewResource extends Resource
{
    protected static ?string $model = SalonReview::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Recensioni';
    protected static string|\UnitEnum|null $navigationGroup = 'Salone';
    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('author_name')
                ->label('Nome cliente')
                ->required()
                ->validationMessages([
                    'required' => 'Il nome cliente è obbligatorio.',
                ]),
            Select::make('rating')
                ->label('Stelle')
                ->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])
                ->required()
                ->validationMessages([
                    'required' => 'Il voto è obbligatorio.',
                ]),
            Textarea::make('body')
                ->label('Testo')
                ->required()
                ->rows(4)
                ->columnSpanFull()
                ->validationMessages([
                    'required' => 'Il testo della recensione è obbligatorio.',
                ]),
            Toggle::make('is_published')
                ->label('Pubblicata'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('author_name')->label('Cliente')->searchable(),
                TextColumn::make('rating')->label('Stelle')
                    ->formatStateUsing(fn ($state) => str_repeat('★', $state)),
                TextColumn::make('body')->label('Testo')->limit(60),
                IconColumn::make('is_published')->label('Pubblicata')->boolean(),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort(fn ($query) => $query->orderByRaw('is_published DESC')->orderBy('created_at'))
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSalonReviews::route('/'),
            'create' => Pages\CreateSalonReview::route('/create'),
            'edit'   => Pages\EditSalonReview::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = \App\Models\SalonReview::whereNull('seen_at')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
