<x-filament-panels::page>
    @include('filament.pages.reports.partials.report-shell', [
        'kicker' => 'Reports / Dealer Reports',
        'titleText' => $titleText,
        'description' => $description,
        'toolbar' => $toolbar,
        'months' => $months,
        'rows' => $rows,
        'labelHeader' => $labelHeader,
        'mode' => 'sales',
    ])
</x-filament-panels::page>