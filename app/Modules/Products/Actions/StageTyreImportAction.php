<?php

namespace App\Modules\Products\Actions;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Products\Enums\TyreImportBatchStatus;
use App\Modules\Products\Enums\TyreImportRowStatus;
use App\Modules\Products\Models\TyreImportBatch;
use App\Modules\Products\Models\TyreImportRow;
use App\Modules\Products\Support\TyreCatalogContract;
use App\Modules\Products\Support\TyreImportFileParser;
use App\Modules\Products\Support\TyreImportRowValidator;
use Illuminate\Support\Facades\DB;

final class StageTyreImportAction
{
    public function __construct(
        private readonly TyreImportFileParser $parser,
        private readonly TyreImportRowValidator $validator,
    ) {
    }

    public function execute(
        Account $account,
        string $filePath,
        ?User $uploadedBy = null,
        ?string $originalFileName = null,
    ): TyreImportBatch {
        $parsed = $this->parser->parse($filePath, $originalFileName);
        $headerValidation = $this->validator->validateHeaders(
            $parsed['source_headers'],
            $parsed['normalized_headers'],
        );

        return DB::transaction(function () use ($account, $uploadedBy, $filePath, $originalFileName, $parsed, $headerValidation) {
            $batch = TyreImportBatch::create([
                'account_id' => $account->id,
                'uploaded_by_user_id' => $uploadedBy?->id,
                'category' => TyreCatalogContract::CATEGORY,
                'source_format' => $parsed['source_format'],
                'source_file_name' => $originalFileName ?? basename($filePath),
                'source_file_size_bytes' => filesize($filePath) ?: null,
                'source_file_hash' => hash_file('sha256', $filePath),
                'sheet_name' => $parsed['sheet_name'],
                'contract_version' => TyreCatalogContract::VERSION,
                'mapping_version' => 'v1',
                'status' => $this->resolveBatchStatus($headerValidation, $parsed['rows']),
                'total_rows' => count($parsed['rows']),
                'staged_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'duplicate_rows' => 0,
                'source_headers' => $parsed['source_headers'],
                'normalized_headers' => $parsed['normalized_headers'],
                'validation_summary' => [
                    'headers' => $headerValidation,
                ],
                'meta' => [
                    'merge_key_fields' => TyreCatalogContract::blueprint()['grouping_rules']['storefront_merge_key']['fields'] ?? [],
                    'supports_formats' => ['xlsx', 'csv'],
                ],
            ]);

            if (! $headerValidation['is_valid'] || $parsed['rows'] === []) {
                return $batch;
            }

            $stats = [
                'staged_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'duplicate_rows' => 0,
            ];

            $seenMergeKeys = [];

            foreach ($parsed['rows'] as $parsedRow) {
                $validation = $this->validator->validateRow($parsedRow['mapped_row']);
                $status = $this->resolveRowStatus($validation['errors']);
                $duplicateOfRowId = null;

                if ($status === TyreImportRowStatus::VALID && $validation['merge_key'] !== null) {
                    if (isset($seenMergeKeys[$validation['merge_key']])) {
                        $status = TyreImportRowStatus::DUPLICATE;
                        $duplicateOfRowId = $seenMergeKeys[$validation['merge_key']]['id'];
                        $validation['warnings'][] = sprintf(
                            'This row duplicates the same merge key as row %d in the same supplier file.',
                            $seenMergeKeys[$validation['merge_key']]['source_row_number'],
                        );
                    }
                }

                $row = TyreImportRow::create([
                    'batch_id' => $batch->id,
                    'account_id' => $account->id,
                    'source_row_number' => $parsedRow['source_row_number'],
                    'status' => $status,
                    'source_sku' => $parsedRow['mapped_row']['sku'] ?? null,
                    'normalized_brand' => $validation['dedupe_signals']['normalized_brand'] ?? null,
                    'normalized_model' => $validation['dedupe_signals']['normalized_model'] ?? null,
                    'normalized_full_size' => $validation['dedupe_signals']['normalized_full_size'] ?? null,
                    'normalized_dot_year' => $validation['dedupe_signals']['normalized_dot_year'] ?? null,
                    'storefront_merge_key' => $validation['merge_key'],
                    'source_row_hash' => hash('sha256', json_encode($parsedRow['raw_row'], JSON_THROW_ON_ERROR)),
                    'normalized_row_hash' => hash('sha256', json_encode($validation['normalized_row'], JSON_THROW_ON_ERROR)),
                    'duplicate_of_row_id' => $duplicateOfRowId,
                    'raw_payload' => $parsedRow['raw_row'],
                    'normalized_payload' => $validation['normalized_row'],
                    'validation_errors' => $validation['errors'],
                    'validation_warnings' => $validation['warnings'],
                    'dedupe_signals' => $validation['dedupe_signals'],
                ]);

                $stats['staged_rows']++;

                if ($status === TyreImportRowStatus::VALID) {
                    $stats['valid_rows']++;
                    if ($validation['merge_key'] !== null) {
                        $seenMergeKeys[$validation['merge_key']] = [
                            'id' => $row->id,
                            'source_row_number' => $parsedRow['source_row_number'],
                        ];
                    }
                    continue;
                }

                if ($status === TyreImportRowStatus::DUPLICATE) {
                    $stats['duplicate_rows']++;
                    continue;
                }

                $stats['invalid_rows']++;
            }

            $batch->update(array_merge($stats, [
                'validation_summary' => [
                    'headers' => $headerValidation,
                    'rows' => [
                        'valid' => $stats['valid_rows'],
                        'invalid' => $stats['invalid_rows'],
                        'duplicate' => $stats['duplicate_rows'],
                    ],
                ],
            ]));

            return $batch->fresh('rows');
        });
    }

    /**
     * @param  array{is_valid: bool}  $headerValidation
     * @param  array<int, mixed>  $rows
     */
    private function resolveBatchStatus(array $headerValidation, array $rows): TyreImportBatchStatus
    {
        if (! $headerValidation['is_valid']) {
            return TyreImportBatchStatus::INVALID_HEADERS;
        }

        if ($rows === []) {
            return TyreImportBatchStatus::EMPTY;
        }

        return TyreImportBatchStatus::STAGED;
    }

    /**
     * @param  array<int, string>  $errors
     */
    private function resolveRowStatus(array $errors): TyreImportRowStatus
    {
        return $errors === [] ? TyreImportRowStatus::VALID : TyreImportRowStatus::INVALID;
    }
}
