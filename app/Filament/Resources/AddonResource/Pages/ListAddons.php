<?php

namespace App\Filament\Resources\AddonResource\Pages;

use App\Filament\Resources\AddonResource;
use App\Models\Addon;
use App\Models\AddonCategory;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAddons extends ListRecords
{
    protected static string $resource = AddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    // Export logic will be implemented
                    $this->notify('success', 'Export functionality coming soon!');
                }),
            Actions\Action::make('bulk_upload_images')
                ->label('Bulk Upload Images')
                ->icon('heroicon-o-photo')
                ->color('info')
                ->action(function () {
                    // Bulk upload logic will be implemented
                    $this->notify('success', 'Bulk upload functionality coming soon!');
                }),
            Actions\CreateAction::make()
                ->label('Add New'),
            Actions\Action::make('bulk_delete')
                ->label('Bulk Delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->deselectRecordsAfterCompletion()
                ->action(function () {
                    $selectedRecords = $this->getSelectedTableRecords();
                    $count = $selectedRecords->count();
                    
                    if ($count === 0) {
                        $this->notify('warning', 'No records selected');
                        return;
                    }
                    
                    $selectedRecords->each->delete();
                    $this->notify('success', "{$count} addon(s) deleted successfully");
                }),
        ];
    }

    public function getTabs(): array
    {
        $categories = AddonCategory::get();
        
        $tabs = [
            'all' => Tab::make('All Addons')
                ->badge(Addon::count()),
        ];

        foreach ($categories as $category) {
            $tabs[$category->slug] = Tab::make($category->name)
                ->badge(Addon::where('addon_category_id', $category->id)->count())
                ->modifyQueryUsing(function (Builder $query) use ($category) {
                    return $query->where('addon_category_id', $category->id);
                });
        }

        return $tabs;
    }
}
