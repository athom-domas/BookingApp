<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BusinessAdminsRelationManager extends RelationManager
{
    protected static string $relationship = 'admins';

    protected static ?string $title = 'Admin';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
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
                Action::make('createAdmin')
                    ->label('Nuovo admin')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique('users', 'email'),
                    ])
                    ->action(function (array $data): void {
                        $business = $this->getOwnerRecord();
                        $tempPassword = Str::random(12);

                        $admin = User::create([
                            'name'                 => 'Admin',
                            'email'                => $data['email'],
                            'password'             => Hash::make($tempPassword),
                            'business_id'          => $business->id,
                            'must_change_password' => true,
                        ]);

                        $admin->assignRole(Role::where('name', 'admin')->where('guard_name', 'web')->firstOrFail());
                        $admin->businesses()->attach($business->id);

                        Notification::make()
                            ->title("Admin creato — Email: {$admin->email} — Password: {$tempPassword}")
                            ->success()
                            ->send();
                    }),

                AttachAction::make()
                    ->label('Admin esistente')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->role('admin'))
                    ->recordSelectSearchColumns(['name', 'email']),
            ])
            ->actions([
                DetachAction::make(),
            ]);
    }
}
