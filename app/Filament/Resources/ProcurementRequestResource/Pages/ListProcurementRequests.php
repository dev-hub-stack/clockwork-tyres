<?php

namespace App\Filament\Resources\ProcurementRequestResource\Pages;

use App\Filament\Resources\ProcurementRequestResource;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProcurementRequests extends ListRecords
{
    protected static string $resource = ProcurementRequestResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Requests')
                ->badge($this->requestCount()),

            'open_queue' => Tab::make('Open Queue')
                ->badge($this->requestCount(function (Builder $query): void {
                    $query->whereIn('current_stage', [
                        ProcurementWorkflowStage::DRAFT->value,
                        ProcurementWorkflowStage::SUBMITTED->value,
                        ProcurementWorkflowStage::SUPPLIER_REVIEW->value,
                        ProcurementWorkflowStage::QUOTED->value,
                        ProcurementWorkflowStage::APPROVED->value,
                    ])->where(function (Builder $query): void {
                        $query->whereNull('meta->last_transition')
                            ->orWhere('meta->last_transition', '!=', 'supplier_revision_requested');
                    });
                }))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    return $query->whereIn('current_stage', [
                        ProcurementWorkflowStage::DRAFT,
                        ProcurementWorkflowStage::SUBMITTED,
                        ProcurementWorkflowStage::SUPPLIER_REVIEW,
                        ProcurementWorkflowStage::QUOTED,
                        ProcurementWorkflowStage::APPROVED,
                    ])->where(function (Builder $query): void {
                        $query->whereNull('meta->last_transition')
                            ->orWhere('meta->last_transition', '!=', 'supplier_revision_requested');
                    });
                }),

            'revision_requested' => Tab::make('Revision Requested')
                ->badge($this->requestCount(function (Builder $query): void {
                    $query->where('current_stage', ProcurementWorkflowStage::SUPPLIER_REVIEW->value)
                        ->where('meta->last_transition', 'supplier_revision_requested');
                }))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    return $query->where('current_stage', ProcurementWorkflowStage::SUPPLIER_REVIEW)
                        ->where('meta->last_transition', 'supplier_revision_requested');
                }),

            'supplier_rejected' => Tab::make("Rejected / Can't Supply")
                ->badge($this->requestCount(function (Builder $query): void {
                    $query->where('current_stage', ProcurementWorkflowStage::CANCELLED->value)
                        ->where('meta->last_transition', 'supplier_rejected');
                }))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    return $query->where('current_stage', ProcurementWorkflowStage::CANCELLED)
                        ->where('meta->last_transition', 'supplier_rejected');
                }),

            'invoiced' => Tab::make('Invoiced Flow')
                ->badge($this->requestCount(function (Builder $query): void {
                    $query->whereIn('current_stage', [
                        ProcurementWorkflowStage::INVOICED->value,
                        ProcurementWorkflowStage::STOCK_RESERVED->value,
                        ProcurementWorkflowStage::STOCK_DEDUCTED->value,
                        ProcurementWorkflowStage::FULFILLED->value,
                    ]);
                }))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    return $query->whereIn('current_stage', [
                        ProcurementWorkflowStage::INVOICED,
                        ProcurementWorkflowStage::STOCK_RESERVED,
                        ProcurementWorkflowStage::STOCK_DEDUCTED,
                        ProcurementWorkflowStage::FULFILLED,
                    ]);
                }),

            'cancelled' => Tab::make('Cancelled')
                ->badge($this->requestCount(function (Builder $query): void {
                    $query->where('current_stage', ProcurementWorkflowStage::CANCELLED->value)
                        ->where(function (Builder $query): void {
                            $query->whereNull('meta->last_transition')
                                ->orWhere('meta->last_transition', '!=', 'supplier_rejected');
                        });
                }))
                ->modifyQueryUsing(function (Builder $query): Builder {
                    return $query->where('current_stage', ProcurementWorkflowStage::CANCELLED)
                        ->where(function (Builder $query): void {
                            $query->whereNull('meta->last_transition')
                                ->orWhere('meta->last_transition', '!=', 'supplier_rejected');
                        });
                }),
        ];
    }

    private function requestCount(?callable $scope = null): int
    {
        $query = clone ProcurementRequestResource::getEloquentQuery();

        if ($scope) {
            $scope($query);
        }

        return $query->count();
    }
}
