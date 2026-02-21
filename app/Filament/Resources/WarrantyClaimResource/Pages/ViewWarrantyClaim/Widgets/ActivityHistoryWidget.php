<?php

namespace App\Filament\Resources\WarrantyClaimResource\Pages\ViewWarrantyClaim\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class ActivityHistoryWidget extends Widget
{
    protected string $view = 'filament.resources.warranty-claim.widgets.activity-history-widget';

    protected int | string | array $columnSpan = 'full';

    public $record;

    public function placeholder(): View
    {
        return view('components.loading-placeholder');
    }

    public function mount($record = null): void
    {
        $this->record = $record ?? $this->getRecord();
    }
    
    protected function getRecord()
    {
        return $this->getPageTableQuery()->find(request()->route('record'));
    }
}
