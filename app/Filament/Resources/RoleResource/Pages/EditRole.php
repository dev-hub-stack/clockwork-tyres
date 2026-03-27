<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function ($record) {
                    // Detach all permissions before deleting
                    $record->syncPermissions([]);
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions_by_group'] = RoleResource::mapPermissionIdsToGroups(
            $this->record->permissions->pluck('id')->map(fn ($id) => (int) $id)->all(),
        );

        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $record->name = $data['name'];
        $record->save();

        $perms = \Spatie\Permission\Models\Permission::whereIn('id', RoleResource::extractPermissionIds($data))->get();
        $record->syncPermissions($perms);

        return $record;
    }
}
