<?php

namespace App\Modules\Products\Support;

use Illuminate\Support\Arr;

final class TyreImportRowValidator
{
    /**
     * @param  array<int, string>  $sourceHeaders
     * @param  array<int, string|null>  $normalizedHeaders
     * @return array{
     *   missing_required_fields: array<int, string>,
     *   duplicate_normalized_headers: array<int, string>,
     *   unmapped_headers: array<int, string>,
     *   is_valid: bool
     * }
     */
    public function validateHeaders(array $sourceHeaders, array $normalizedHeaders): array
    {
        $requiredFields = TyreCatalogContract::blueprint()['required_fields'] ?? [];
        $mappedFields = array_values(array_filter($normalizedHeaders));
        $missingRequiredFields = array_values(array_diff($requiredFields, $mappedFields));
        $duplicateHeaders = $this->duplicateHeaders($normalizedHeaders);
        $unmappedHeaders = [];

        foreach ($sourceHeaders as $index => $sourceHeader) {
            if (($normalizedHeaders[$index] ?? null) === null && $sourceHeader !== '') {
                $unmappedHeaders[] = $sourceHeader;
            }
        }

        return [
            'missing_required_fields' => $missingRequiredFields,
            'duplicate_normalized_headers' => $duplicateHeaders,
            'unmapped_headers' => $unmappedHeaders,
            'is_valid' => $missingRequiredFields === [] && $duplicateHeaders === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $mappedRow
     * @return array{
     *   normalized_row: array<string, mixed>,
     *   errors: array<int, string>,
     *   warnings: array<int, string>,
     *   dedupe_signals: array<string, mixed>,
     *   merge_key: string|null
     * }
     */
    public function validateRow(array $mappedRow): array
    {
        $normalized = $this->normalizeRow($mappedRow);
        $errors = [];
        $warnings = [];

        foreach (TyreCatalogContract::blueprint()['required_fields'] ?? [] as $requiredField) {
            if ($this->isBlank($normalized[$requiredField] ?? null)) {
                $errors[] = "The {$requiredField} field is required.";
            }
        }

        $this->validateIntegerField($normalized, 'width', $errors);
        $this->validateIntegerField($normalized, 'height', $errors);
        $this->validateNumericField($normalized, 'rim_size', $errors, allowDecimal: true);
        $this->validateTextField($normalized, 'load_index', $errors);
        $this->validateTextField($normalized, 'speed_rating', $errors);

        foreach (array_keys(TyreCatalogContract::blueprint()['pricing_columns'] ?? []) as $pricingField) {
            $this->validateNumericField($normalized, $pricingField, $errors, minimum: 0);
        }

        foreach (TyreCatalogContract::blueprint()['boolean_like_fields'] ?? [] as $booleanField) {
            $value = $normalized[$booleanField] ?? null;

            if ($value === null) {
                continue;
            }

            $normalizedBoolean = $this->normalizeBooleanLike($value);

            if ($normalizedBoolean === null) {
                $errors[] = "The {$booleanField} field must be YES/NO, TRUE/FALSE, or 1/0.";
                continue;
            }

            $normalized[$booleanField] = $normalizedBoolean;
        }

        if (($normalized['speed_rating'] ?? null) !== null) {
            $normalized['speed_rating'] = strtoupper((string) $normalized['speed_rating']);
        }

        $canonicalSize = $this->buildCanonicalSize($normalized);
        if ($canonicalSize !== null) {
            $normalized['canonical_size'] = $canonicalSize;
        }

        if ($canonicalSize !== null && ($normalized['full_size'] ?? null) !== null) {
            $normalizedFullSize = strtoupper(str_replace(' ', '', (string) $normalized['full_size']));
            if ($normalizedFullSize !== $canonicalSize) {
                $warnings[] = 'The full_size value does not match the numeric dimensions; canonical size was used for grouping.';
            }
        }

        $mergeYear = $this->resolveMergeYear($normalized['dot'] ?? null, $warnings);
        $dedupeSignals = [
            'source_sku' => $normalized['sku'] ?? null,
            'normalized_brand' => $this->normalizeTokenForKey($normalized['brand'] ?? null),
            'normalized_model' => $this->normalizeTokenForKey($normalized['model'] ?? null),
            'normalized_full_size' => $canonicalSize,
            'normalized_dot_year' => $mergeYear,
        ];

        $mergeKey = null;
        if ($dedupeSignals['normalized_brand'] !== null && $dedupeSignals['normalized_model'] !== null && $canonicalSize !== null) {
            $mergeKey = hash('sha256', implode('|', [
                $dedupeSignals['normalized_brand'],
                $dedupeSignals['normalized_model'],
                $canonicalSize,
                $mergeYear ?? 'unknown',
            ]));
        }

        return [
            'normalized_row' => $normalized,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'dedupe_signals' => $dedupeSignals,
            'merge_key' => $mergeKey,
        ];
    }

    /**
     * @param  array<int, string|null>  $normalizedHeaders
     * @return array<int, string>
     */
    private function duplicateHeaders(array $normalizedHeaders): array
    {
        $counts = array_count_values(array_filter($normalizedHeaders));

        return array_values(array_keys(array_filter($counts, fn (int $count) => $count > 1)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $field => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $normalized[$field] = $trimmed === '' ? null : $trimmed;
                continue;
            }

            $normalized[$field] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $errors
     */
    private function validateIntegerField(array &$row, string $field, array &$errors): void
    {
        $this->validateNumericField($row, $field, $errors);

        if ($this->isBlank($row[$field] ?? null)) {
            return;
        }

        if ((int) $row[$field] != $row[$field]) {
            $errors[] = "The {$field} field must be a whole number.";
            return;
        }

        $row[$field] = (int) $row[$field];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $errors
     */
    private function validateNumericField(
        array &$row,
        string $field,
        array &$errors,
        bool $allowDecimal = false,
        float $minimum = 0.00001
    ): void {
        if ($this->isBlank($row[$field] ?? null)) {
            return;
        }

        if (! is_numeric($row[$field])) {
            $errors[] = "The {$field} field must be numeric.";
            return;
        }

        $numericValue = (float) $row[$field];

        if ($numericValue < $minimum) {
            $errors[] = "The {$field} field must be greater than or equal to {$minimum}.";
            return;
        }

        $row[$field] = $allowDecimal && floor($numericValue) !== $numericValue
            ? $numericValue
            : (int) $numericValue;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $errors
     */
    private function validateTextField(array &$row, string $field, array &$errors): void
    {
        if ($this->isBlank($row[$field] ?? null)) {
            return;
        }

        $row[$field] = trim((string) $row[$field]);

        if ($row[$field] === '') {
            $errors[] = "The {$field} field is required.";
        }
    }

    private function normalizeBooleanLike(mixed $value): ?bool
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            '' => null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function buildCanonicalSize(array $row): ?string
    {
        if ($this->isBlank(Arr::get($row, 'width')) || $this->isBlank(Arr::get($row, 'height')) || $this->isBlank(Arr::get($row, 'rim_size'))) {
            return null;
        }

        $rimSize = $this->formatNumericSegment($row['rim_size']);

        return strtoupper(sprintf(
            '%s/%sR%s',
            $this->formatNumericSegment($row['width']),
            $this->formatNumericSegment($row['height']),
            $rimSize,
        ));
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveMergeYear(mixed $dotValue, array &$warnings): ?string
    {
        if ($this->isBlank($dotValue)) {
            $warnings[] = 'The DOT field is blank, so the grouping year defaults to unknown.';
            return 'unknown';
        }

        $dot = trim((string) $dotValue);

        if (preg_match('/^\d{4}$/', $dot) === 1) {
            return $dot;
        }

        $warnings[] = 'The DOT field is ambiguous, so the grouping year defaults to unknown.';

        return 'unknown';
    }

    private function normalizeTokenForKey(mixed $value): ?string
    {
        if ($this->isBlank($value)) {
            return null;
        }

        return strtoupper(trim((string) $value));
    }

    private function formatNumericSegment(mixed $value): string
    {
        $numericValue = (float) $value;

        if (floor($numericValue) === $numericValue) {
            return (string) (int) $numericValue;
        }

        return rtrim(rtrim((string) $numericValue, '0'), '.');
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
