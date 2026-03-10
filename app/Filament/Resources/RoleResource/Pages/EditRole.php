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
        // Pre-populate the checkbox list with current permission IDs
        $data['permissions'] = $this->record->permissions->pluck('id')->toArray();
        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $record->name = $data['name'];
        $record->save();

        $perms = \Spatie\Permission\Models\Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $record->syncPermissions($perms);

        return $record;
    }
}
