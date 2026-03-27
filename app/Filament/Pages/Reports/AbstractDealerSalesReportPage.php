<?php

namespace App\Filament\Pages\Reports;

use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\DB;

abstract class AbstractDealerSalesReportPage extends AbstractSalesReportPage
{
    protected string $view = 'filament.pages.reports.dealer-sales-report';

    public function mount(): void
    {
        parent::mount();

        $dealerOptions = $this->dealerOptions();

        if ($this->dealerId === null || ! array_key_exists($this->dealerId, $dealerOptions)) {
            $this->dealerId = array_key_first($dealerOptions);
        }
    }

    protected function getFiltersArray(): array
    {
        return array_merge(parent::getFiltersArray(), [
            'channel' => 'wholesale',
            'dealer_id' => $this->dealerId,
        ]);
    }

    protected function showUserFilter(): bool
    {
        return false;
    }

    protected function showChannelFilter(): bool
    {
        return false;
    }

    protected static function requiredReportPermission(): string
    {
        return 'view_dealer_reports';
    }

    protected function dealerOptions(): array
    {
        $sortExpression = DB::getDriverName() === 'sqlite'
            ? "COALESCE(NULLIF(business_name, ''), TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')))"
            : 'COALESCE(NULLIF(business_name, ""), CONCAT(first_name, " ", last_name))';

        return Customer::query()
            ->where('customer_type', 'wholesale')
            ->orderByRaw($sortExpression . ' asc')
            ->get()
            ->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->name])
            ->all();
    }
}