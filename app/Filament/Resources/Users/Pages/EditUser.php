<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load user's roles and permissions
        $user = $this->record;
        
        $data['role'] = $user->roles->first()?->name;
        $data['permissions'] = $user->permissions->pluck('name')->toArray();
        
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hash password only if changed
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }
        
        // Remove role and permissions from data (will be synced separately)
        unset($data['role'], $data['permissions']);
        
        return $data;
    }
    
    protected function afterSave(): void
    {
        $formData = $this->form->getState();
        $user = $this->record;
        
        // Sync role
        if (isset($formData['role'])) {
            $user->syncRoles([$formData['role']]);
        }
        
        // Sync permissions (only if not admin)
        if (isset($formData['permissions']) && $formData['role'] !== 'admin') {
            $user->syncPermissions($formData['permissions']);
        } elseif ($formData['role'] === 'admin') {
            // Admin gets all permissions
            $user->syncPermissions(\Spatie\Permission\Models\Permission::all());
        }
    }
}
