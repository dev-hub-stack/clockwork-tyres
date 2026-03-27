<x-filament-panels::page>
    @include('filament.pages.reports.partials.report-shell', [
        'kicker' => $kicker,
        'titleText' => $titleText,
        'description' => $description,
        'toolbar' => $toolbar,
        'months' => $months,
        'rows' => $rows,
        'labelHeader' => $labelHeader,
        'mode' => 'profit',
    ])
</x-filament-panels::page>