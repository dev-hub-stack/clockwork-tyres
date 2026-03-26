<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $role = \Spatie\Permission\Models\Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        $permissionIds = RoleResource::extractPermissionIds($data);

        if (! empty($permissionIds)) {
            $perms = \Spatie\Permission\Models\Permission::whereIn('id', $permissionIds)->get();
            $role->syncPermissions($perms);
        }

        return $role;
    }
}
