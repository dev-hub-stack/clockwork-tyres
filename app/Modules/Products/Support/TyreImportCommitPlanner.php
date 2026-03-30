<?php

namespace App\Modules\Products\Support;

use App\Modules\Products\Enums\TyreImportRowStatus;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Models\TyreImportRow;
use Illuminate\Support\Collection;

final class TyreImportCommitPlanner
{
    /**
     * @return array{
     *   summary_cards: array<int, array{label: string, value: int, note: string}>,
     *   group_rows: array<int, array<string, mixed>>,
     *   scope_note: string
     * }
     */
    public function plan(TyreImportBatch $batch): array
    {
        $rows = $this->rowsForBatch($batch);

        /** @var Collection<int, TyreImportRow> $validRows */
        $validRows = $rows
            ->filter(fn (TyreImportRow $row): bool => $row->status === TyreImportRowStatus::VALID)
            ->filter(fn (TyreImportRow $row): bool => filled($row->storefront_merge_key))
            ->values();

        $blockedRows = $rows->reject(
            fn (TyreImportRow $row): bool => $row->status === TyreImportRowStatus::VALID
        );

        $validMergeKeys = $validRows
            ->pluck('storefront_merge_key')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingGroups = $this->existingRowsForBatch($batch, $validMergeKeys)
            ->groupBy('storefront_merge_key');

        $groupRows = $validRows
            ->groupBy('storefront_merge_key')
            ->map(function (Collection $group, string $mergeKey) use ($existingGroups): array {
                /** @var TyreImportRow $firstRow */
                $firstRow = $group->first();
                $payload = $firstRow->normalized_payload ?? [];
                $existingMatches = $existingGroups->get($mergeKey, collect());
                $actionKey = $existingMatches->isEmpty() ? 'create_group' : 'merge_group';

                return [
                    'merge_key' => $mergeKey,
                    'action_key' => $actionKey,
                    'action_label' => $actionKey === 'create_group' ? 'Create new tyre group' : 'Merge into existing staged group',
                    'brand' => $payload['brand'] ?? $firstRow->normalized_brand ?? '--',
                    'model' => $payload['model'] ?? $firstRow->normalized_model ?? '--',
                    'full_size' => $payload['full_size'] ?? ($payload['canonical_size'] ?? $firstRow->normalized_full_size ?? '--'),
                    'dot_year' => $firstRow->normalized_dot_year ?? ($payload['dot'] ?? '--'),
                    'source_row_count' => $group->count(),
                    'previous_match_count' => $existingMatches->count(),
                    'source_rows' => $group->pluck('source_row_number')->sort()->values()->all(),
                    'supplier_skus' => $group->pluck('source_sku')->filter()->unique()->values()->all(),
                    'warning_count' => $group->sum(
                        fn (TyreImportRow $row): int => count($row->validation_warnings ?? [])
                    ),
                    'note' => $actionKey === 'create_group'
                        ? 'No prior staged rows for this merge key were found in the current account.'
                        : 'This merge key already exists in an earlier staged batch for the same account.',
                ];
            })
            ->sortBy([
                ['action_key', 'asc'],
                ['brand', 'asc'],
                ['model', 'asc'],
                ['full_size', 'asc'],
            ])
            ->values()
            ->all();

        $newGroupsCount = collect($groupRows)->where('action_key', 'create_group')->count();
        $mergeGroupsCount = collect($groupRows)->where('action_key', 'merge_group')->count();

        return [
            'summary_cards' => [
                [
                    'label' => 'New groups',
                    'value' => $newGroupsCount,
                    'note' => 'Valid merge groups that do not exist in prior staged batches for this account.',
                ],
                [
                    'label' => 'Merge groups',
                    'value' => $mergeGroupsCount,
                    'note' => 'Valid merge groups that already exist in earlier staged batches for this account.',
                ],
                [
                    'label' => 'Valid staged rows',
                    'value' => $validRows->count(),
                    'note' => 'Rows included in the current read-only commit plan.',
                ],
                [
                    'label' => 'Blocked rows',
                    'value' => $blockedRows->count(),
                    'note' => 'Invalid or duplicate rows that must stay out of the commit plan.',
                ],
            ],
            'group_rows' => $groupRows,
            'scope_note' => 'Planner compares only against prior staged rows for the same account. Other accounts never affect the result.',
        ];
    }

    /**
     * @return Collection<int, TyreImportRow>
     */
    private function rowsForBatch(TyreImportBatch $batch): Collection
    {
        return TyreImportRow::query()
            ->where('batch_id', $batch->id)
            ->orderBy('source_row_number')
            ->get();
    }

    /**
     * @param  array<int, string>  $mergeKeys
     * @return Collection<int, TyreImportRow>
     */
    private function existingRowsForBatch(TyreImportBatch $batch, array $mergeKeys): Collection
    {
        if ($mergeKeys === []) {
            return collect();
        }

        return TyreImportRow::query()
            ->where('account_id', $batch->account_id)
            ->where('batch_id', '!=', $batch->id)
            ->where('status', TyreImportRowStatus::VALID->value)
            ->whereIn('storefront_merge_key', $mergeKeys)
            ->orderBy('batch_id')
            ->orderBy('source_row_number')
            ->get();
    }
}
