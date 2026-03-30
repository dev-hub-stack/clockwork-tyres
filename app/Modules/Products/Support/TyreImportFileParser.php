<?php

namespace App\Modules\Products\Support;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class TyreImportFileParser
{
    /**
     * @return array{
     *   source_format: string,
     *   sheet_name: string|null,
     *   source_headers: array<int, string>,
     *   normalized_headers: array<int, string|null>,
     *   rows: array<int, array{
     *     source_row_number: int,
     *     raw_row: array<string, mixed>,
     *     mapped_row: array<string, mixed>
     *   }>
     * }
     */
    public function parse(string $filePath, ?string $originalFileName = null): array
    {
        if (! is_file($filePath)) {
            throw new InvalidArgumentException('The tyre import file could not be found.');
        }

        $sourceFormat = $this->detectSupportedFormat($originalFileName ?? $filePath);
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getSheet(0);
        $rows = $worksheet->toArray(null, false, false, false);

        if ($rows === [] || $this->isEmptyRow($rows[0])) {
            return [
                'source_format' => $sourceFormat,
                'sheet_name' => $worksheet->getTitle(),
                'source_headers' => [],
                'normalized_headers' => [],
                'rows' => [],
            ];
        }

        $sourceHeaders = array_map([$this, 'normalizeHeaderLabel'], $rows[0]);
        $headerFieldMap = $this->headerFieldMap();
        $normalizedHeaders = array_map(function (?string $header) use ($headerFieldMap) {
            if ($header === null || $header === '') {
                return null;
            }

            $headerKey = $this->normalizeHeaderKey($header);

            return $headerFieldMap[$headerKey] ?? null;
        }, $sourceHeaders);

        $mappedRows = [];

        foreach (array_slice($rows, 1) as $offset => $rowValues) {
            if ($this->isEmptyRow($rowValues)) {
                continue;
            }

            $rawRow = [];
            $mappedRow = [];

            foreach ($sourceHeaders as $index => $header) {
                $columnKey = $header !== '' ? $header : 'column_'.($index + 1);
                $value = $this->normalizeCellValue($rowValues[$index] ?? null);
                $rawRow[$columnKey] = $value;

                $mappedField = $normalizedHeaders[$index] ?? null;
                if ($mappedField !== null) {
                    $mappedRow[$mappedField] = $value;
                }
            }

            $mappedRows[] = [
                'source_row_number' => $offset + 2,
                'raw_row' => $rawRow,
                'mapped_row' => $mappedRow,
            ];
        }

        return [
            'source_format' => $sourceFormat,
            'sheet_name' => $worksheet->getTitle(),
            'source_headers' => $sourceHeaders,
            'normalized_headers' => $normalizedHeaders,
            'rows' => $mappedRows,
        ];
    }

    private function detectSupportedFormat(string $fileName): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Tyre import supports only XLSX and CSV files.');
        }

        return $extension;
    }

    /**
     * @return array<string, string>
     */
    private function headerFieldMap(): array
    {
        $map = [];

        foreach (TyreCatalogContract::blueprint()['source_columns'] as $column) {
            $map[$this->normalizeHeaderKey((string) $column['source_header'])] = (string) $column['field'];
            $map[$this->normalizeHeaderKey((string) $column['field'])] = (string) $column['field'];
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->normalizeCellValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeaderLabel(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $header = trim((string) $value);

        return ltrim($header, "\xEF\xBB\xBF");
    }

    private function normalizeHeaderKey(string $value): string
    {
        $header = strtolower(trim($value));
        $header = ltrim($header, "\xEF\xBB\xBF");
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;

        return trim($header, '_');
    }

    private function normalizeCellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        return $value;
    }
}
