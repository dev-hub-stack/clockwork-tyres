<?php

namespace App\Filament\Pages\Concerns;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

trait HasReportFilters
{
    public string $startMonth = '';

    public string $endMonth = '';

    public string $sort = 'alpha';

    public string $channel = 'all';

    public ?int $dealerId = null;

    public ?int $userId = null;

    protected function initializeReportFilters(): void
    {
        $now = now();
        $query = request()->query();

        $this->startMonth = (string) ($query['start_month'] ?? $now->copy()->startOfYear()->format('Y-m'));
        $this->endMonth = (string) ($query['end_month'] ?? $now->copy()->format('Y-m'));
        $this->sort = (string) ($query['sort'] ?? 'alpha');
        $this->channel = (string) ($query['channel'] ?? 'all');
        $this->dealerId = filled($query['dealer_id'] ?? null) ? (int) $query['dealer_id'] : null;
        $this->userId = filled($query['user_id'] ?? null) ? (int) $query['user_id'] : null;

        if ($this->startMonth > $this->endMonth) {
            [$this->startMonth, $this->endMonth] = [$this->endMonth, $this->startMonth];
        }
    }

    protected function reportStartDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->startMonth)->startOfMonth();
    }

    protected function reportEndDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->endMonth)->endOfMonth();
    }

    protected function getFiltersArray(): array
    {
        return [
            'channel' => $this->channel,
            'dealer_id' => $this->dealerId,
            'user_id' => $this->userId,
        ];
    }

    protected function applySort(Collection $rows): Collection
    {
        return match ($this->sort) {
            'qty_desc' => $rows->sortByDesc('total_qty')->values(),
            'value_desc' => $rows->sortByDesc('total_value')->values(),
            default => $rows->sortBy(fn (array $row) => mb_strtolower($row['label']))->values(),
        };
    }

    protected function applyMetricSort(Collection $rows, string $valueKey): Collection
    {
        return match ($this->sort) {
            'value_desc' => $rows->sortByDesc($valueKey)->values(),
            default => $rows->sortBy(fn (array $row) => mb_strtolower($row['label']))->values(),
        };
    }

    protected function dealerOptions(): array
    {
        return Customer::query()
            ->whereIn('customer_type', ['dealer', 'wholesale'])
            ->orderByRaw('COALESCE(NULLIF(business_name, ""), CONCAT(first_name, " ", last_name)) asc')
            ->get()
            ->mapWithKeys(fn (Customer $customer) => [$customer->id => $customer->name])
            ->all();
    }

    protected function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}