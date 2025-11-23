<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }
        
        // Remove role and permissions from data (will be synced separately)
        unset($data['role'], $data['permissions']);
        
        return $data;
    }
    
    protected function handleRecordCreation(array $data): Model
    {
        $formData = $this->form->getState();
        
        // Create the user
        $user = static::getModel()::create($data);
        
        // Assign role
        if (isset($formData['role'])) {
            $user->assignRole($formData['role']);
        }
        
        // Sync permissions (only if not admin, admin gets all)
        if (isset($formData['permissions']) && $formData['role'] !== 'admin') {
            $user->syncPermissions($formData['permissions']);
        }
        
        return $user;
    }
}
