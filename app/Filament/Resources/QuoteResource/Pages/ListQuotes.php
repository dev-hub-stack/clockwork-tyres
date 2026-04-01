<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Quote')
                ->icon('heroicon-o-plus'),
        ];
    }
    
    public function getTitle(): string
    {
        return 'Quotes & Proformas';
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Quotes')
                ->badge($this->quoteCount()),

            'direct' => Tab::make('Direct Quotes')
                ->badge($this->quoteCount(fn (Builder $query) => $query->whereDoesntHave('procurementQuoteRequest')))
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereDoesntHave('procurementQuoteRequest');
                }),

            'procurement' => Tab::make('Procurement Quotes')
                ->badge($this->quoteCount(fn (Builder $query) => $query->whereHas('procurementQuoteRequest')))
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->whereHas('procurementQuoteRequest');
                }),

            'supplier_intake' => Tab::make('Supplier Intake')
                ->visible(fn (): bool => $this->currentAccount()?->supportsWholesalePortal() ?? false)
                ->badge($this->quoteCount(function (Builder $query): void {
                    $currentAccount = $this->currentAccount();

                    if (! $currentAccount) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereHas('procurementQuoteRequest', function (Builder $procurementQuery) use ($currentAccount): void {
                        $procurementQuery
                            ->where('supplier_account_id', $currentAccount->id)
                            ->whereIn('current_stage', [
                                ProcurementWorkflowStage::DRAFT->value,
                                ProcurementWorkflowStage::SUBMITTED->value,
                                ProcurementWorkflowStage::SUPPLIER_REVIEW->value,
                                ProcurementWorkflowStage::QUOTED->value,
                                ProcurementWorkflowStage::APPROVED->value,
                            ]);
                    });
                }))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    $currentAccount = $this->currentAccount();

                    if (! $currentAccount) {
                        return $query->whereRaw('1 = 0');
                    }

                    return $query->whereHas('procurementQuoteRequest', function (Builder $procurementQuery) use ($currentAccount): void {
                        $procurementQuery
                            ->where('supplier_account_id', $currentAccount->id)
                            ->whereIn('current_stage', [
                                ProcurementWorkflowStage::DRAFT,
                                ProcurementWorkflowStage::SUBMITTED,
                                ProcurementWorkflowStage::SUPPLIER_REVIEW,
                                ProcurementWorkflowStage::QUOTED,
                                ProcurementWorkflowStage::APPROVED,
                            ]);
                    });
                }),
        ];
    }

    private function quoteCount(?callable $scope = null): int
    {
        $query = clone QuoteResource::getEloquentQuery();

        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }

    private function currentAccount()
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('super_admin')) {
            return null;
        }

        return app(CurrentAccountResolver::class)->resolve(request(), $user)->currentAccount;
    }
}
