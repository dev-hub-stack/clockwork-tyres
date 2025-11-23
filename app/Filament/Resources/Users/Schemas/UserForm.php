<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                            
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            
                            TextInput::make('password')
                                ->password()
                                ->required(fn($context) => $context === 'create')
                                ->dehydrated(fn($state) => filled($state))
                                ->revealable()
                                ->helperText('Leave blank to keep current password'),
                            
                            Select::make('role')
                                ->label('Role')
                                ->options(Role::all()->pluck('name', 'name'))
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if (!$state) {
                                        $set('permissions', []);
                                        return;
                                    }

                                    // Auto-select permissions based on the selected role
                                    // This acts as a "template" for the user
                                    $role = Role::where('name', $state)->first();
                                    if ($role) {
                                        $set('permissions', $role->permissions->pluck('name')->toArray());
                                    }
                                })
                                ->helperText('Select a role to inherit standard permissions, or leave blank to build a custom permission set manually.'),

                            \Filament\Forms\Components\Placeholder::make('role_permissions')
                                ->label('Permissions included in Role:')
                                ->content(function (Get $get) {
                                    $roleName = $get('role');
                                    if (!$roleName) return 'None';
                                    if ($roleName === 'admin') return 'All Permissions';
                                    
                                    $role = Role::where('name', $roleName)->first();
                                    if (!$role) return 'None';
                                    
                                    return collect($role->permissions)
                                        ->pluck('name')
                                        ->map(fn($name) => ucwords(str_replace('_', ' ', $name)))
                                        ->join(', ');
                                })
                                ->visible(fn (Get $get) => filled($get('role')))
                                ->columnSpanFull(),
                        ]),
                    ]),
                
                Section::make('Permissions')
                    ->description('Users automatically inherit permissions from their assigned Role. Select items below ONLY if you want to grant EXTRA access beyond the role.')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->label('')
                            ->options(function () {
                                return Permission::all()
                                    ->mapWithKeys(fn($p) => [$p->name => ucwords(str_replace('_', ' ', $p->name))]);
                            })
                            ->columns(4)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->disabled(fn(Get $get) => $get('role') === 'admin')
                            ->helperText(fn(Get $get) => 
                                $get('role') === 'admin' 
                                    ? 'All permissions are automatically enabled for Administrator accounts' 
                                    : null
                            ),
                    ]),
            ]);
    }
}
