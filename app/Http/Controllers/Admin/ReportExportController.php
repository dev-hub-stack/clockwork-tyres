<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function download(Request $request, string $report, string $format, ReportExportService $reportExportService)
    {
        abort_unless(auth()->user()?->can('view_reports') ?? false, 403);

        $payload = $reportExportService->build($report, $request->query());

        return match ($format) {
            'csv' => $this->downloadCsv($payload, $reportExportService),
            'pdf' => $this->downloadPdf($payload, $reportExportService),
            default => abort(404),
        };
    }

    private function downloadCsv(array $payload, ReportExportService $reportExportService): StreamedResponse
    {
        return response()->streamDownload(function () use ($payload) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [$payload['title']]);
            fputcsv($handle, ['Range', $payload['filters']['startMonth'] . ' to ' . $payload['filters']['endMonth']]);
            fputcsv($handle, ['Channel', ucfirst((string) $payload['filters']['channel'])]);

            if (! empty($payload['filters']['dealer'])) {
                fputcsv($handle, ['Dealer', $payload['filters']['dealer']]);
            }

            if (! empty($payload['filters']['user'])) {
                fputcsv($handle, ['User', $payload['filters']['user']]);
            }

            fputcsv($handle, []);

            match ($payload['type']) {
                'pivot' => $this->writePivotCsv($handle, $payload),
                'orders' => $this->writeOrdersCsv($handle, $payload),
                'team' => $this->writeTeamCsv($handle, $payload),
                default => null,
            };

            fclose($handle);
        }, $reportExportService->csvFilename($payload), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function downloadPdf(array $payload, ReportExportService $reportExportService)
    {
        return Pdf::loadView('exports.report', ['payload' => $payload])
            ->setPaper('a4', 'landscape')
            ->download($reportExportService->pdfFilename($payload));
    }

    private function writePivotCsv($handle, array $payload): void
    {
        $header = [$payload['labelHeader']];
        foreach ($payload['months'] as $month) {
            if ($payload['mode'] === 'profit') {
                $header[] = $month['label'] . ' Profit';
            } elseif ($payload['mode'] === 'inventory') {
                $header[] = $month['label'] . ' Added';
                $header[] = $month['label'] . ' Sold';
            } else {
                $header[] = $month['label'] . ' Qty';
                $header[] = $month['label'] . ' Value';
            }
        }

        if ($payload['mode'] === 'profit') {
            $header[] = 'Total Profit';
        } elseif ($payload['mode'] === 'inventory') {
            $header[] = 'Total Added';
            $header[] = 'Total Sold';
        } else {
            $header[] = 'Total Qty';
            $header[] = 'Total Value';
        }

        fputcsv($handle, $header);

        foreach ($payload['rows'] as $row) {
            $line = [$row['label']];
            foreach ($payload['months'] as $month) {
                $monthData = $row['months'][$month['key']] ?? [];
                if ($payload['mode'] === 'profit') {
                    $line[] = $monthData['profit'] ?? 0;
                } elseif ($payload['mode'] === 'inventory') {
                    $line[] = $monthData['added'] ?? 0;
                    $line[] = $monthData['sold'] ?? 0;
                } else {
                    $line[] = $monthData['qty'] ?? 0;
                    $line[] = $monthData['value'] ?? 0;
                }
            }

            if ($payload['mode'] === 'profit') {
                $line[] = $row['total_profit'];
            } elseif ($payload['mode'] === 'inventory') {
                $line[] = $row['total_added'];
                $line[] = $row['total_sold'];
            } else {
                $line[] = $row['total_qty'];
                $line[] = $row['total_value'];
            }

            fputcsv($handle, $line);
        }
    }

    private function writeOrdersCsv($handle, array $payload): void
    {
        fputcsv($handle, ['Invoice', 'Description', 'Customer', 'Date', 'Value', 'Profit']);

        foreach ($payload['rows'] as $row) {
            fputcsv($handle, [
                $row['invoice_number'],
                $row['description'],
                $row['customer_name'],
                $row['issued_on'],
                $row['value'],
                $row['profit'],
            ]);
        }

        fputcsv($handle, ['TOTAL', '', '', '', $payload['totals']['value'], $payload['totals']['profit']]);
    }

    private function writeTeamCsv($handle, array $payload): void
    {
        fputcsv($handle, ['Comparison Table']);

        $header = ['User'];
        foreach ($payload['months'] as $month) {
            $header[] = $month['label'] . ' Qty';
            $header[] = $month['label'] . ' Value';
        }
        $header[] = 'Total Qty';
        $header[] = 'Total Value';
        fputcsv($handle, $header);

        foreach ($payload['rows'] as $row) {
            $line = [$row['label']];
            foreach ($payload['months'] as $month) {
                $monthData = $row['months'][$month['key']] ?? ['qty' => 0, 'value' => 0];
                $line[] = $monthData['qty'];
                $line[] = $monthData['value'];
            }
            $line[] = $row['total_qty'];
            $line[] = $row['total_value'];
            fputcsv($handle, $line);
        }

        fputcsv($handle, []);
        fputcsv($handle, [$payload['selectedUserName'] ? $payload['selectedUserName'] . ' Detail' : 'User Detail']);
        fputcsv($handle, ['Invoice', 'Description', 'Customer', 'Date', 'Value', 'Profit']);

        foreach ($payload['detailRows'] as $row) {
            fputcsv($handle, [
                $row['invoice_number'],
                $row['description'],
                $row['customer_name'],
                $row['issued_on'],
                $row['value'],
                $row['profit'],
            ]);
        }

        fputcsv($handle, ['TOTAL', '', '', '', $payload['detailTotals']['value'], $payload['detailTotals']['profit']]);
    }
}