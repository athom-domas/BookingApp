<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers;

use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessAdminsRelationManager extends RelationManager
{
    protected static string $relationship = 'admins';

    protected static ?string $title = 'Admin';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('business.name')->label('Business principale'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->role('admin'))
                    ->recordSelectSearchColumns(['name', 'email']),
            ])
            ->actions([
                DetachAction::make(),
            ]);
    }
}
