<x-filament-panels::page>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        .page-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }

        #tyresGrid {
            min-height: 600px;
            width: 100% !important;
        }

        .pq-grid,
        .pq-grid-cont {
            width: 100% !important;
        }

        .pq-pager {
            display: flex !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 4px !important;
            padding: 4px 8px !important;
            min-height: 36px !important;
            background: #f3f4f6 !important;
            border-top: 1px solid #d1d5db !important;
            overflow: visible !important;
        }

        .pq-grid-header-search-row {
            display: table-row !important;
            visibility: visible !important;
            background-color: #f8f9fa !important;
        }

        .pq-grid-header-search-row .pq-grid-col {
            padding: 5px !important;
            background: #f8f9fa !important;
            border-color: #e5e7eb !important;
        }

        .pq-grid-hd-search-field {
            display: block !important;
            width: 100% !important;
            padding: 5px 8px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 4px !important;
            font-size: 13px !important;
            background: #fff !important;
            color: #111827 !important;
        }

        .pq-grid-hd-search-field:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1) !important;
        }

        .pq-grid-col,
        .pq-grid-number-cell {
            border-color: #e5e7eb !important;
            background-color: #f3f4f6 !important;
        }

        .pq-grid-title-row .pq-grid-number-cell,
        .pq-grid-header-search-row .pq-grid-number-cell,
        .pq-grid-title-row .ui-state-default {
            background-color: #374151 !important;
            color: #fff !important;
        }

        .pq-grid-row.pq-striped {
            background: #f9fafb;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .action-buttons .btn[disabled] {
            opacity: 0.7;
            cursor: not-allowed;
        }
    </style>

    <div class="space-y-6">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">
                        {{ $category_definition['label'] ?? 'Tyres' }}
                    </p>
                    @if (($category_definition['launch_status'] ?? null) === 'launch')
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-800">
                            Launch category
                        </span>
                    @endif
                </div>

                <div>
                    <h1 class="text-xl font-semibold">Tyres Grid</h1>
                    <p class="text-sm text-amber-900">
                        This tyre admin page now follows the same CRM grid layout language as the existing product and inventory grids. The sample sheet is still pending, so the column set stays generic until the real tyre headers arrive.
                    </p>
                </div>

                @if (!empty($launch_notes))
                    <ul class="list-disc space-y-1 pl-5 text-sm text-amber-900">
                        @foreach ($launch_notes as $note)
                            <li>{{ ucfirst($note) }}.</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="alert alert-info mb-0" role="alert">
            <strong><i class="bi bi-info-circle"></i> CRM layout reuse:</strong>
            This page is using the same pqGrid-style layout direction as the wheel product grid. Inventory will follow the existing inventory grid pattern as the tyre schema is finalized.
        </div>

        <div class="action-buttons">
            @foreach ($toolbar_actions as $action)
                <button
                    type="button"
                    class="btn btn-{{ $action['variant'] }}"
                    id="{{ $action['id'] }}"
                    title="{{ $action['hint'] }}"
                    @if($action['disabled']) disabled @endif
                >
                    <i class="{{ $action['icon'] }}"></i> {{ $action['label'] }}
                </button>
            @endforeach
        </div>

        <div class="page-content">
            <div id="tyresGrid"></div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>

    <script>
        window.tyreGridData = @json($tyres_data);
        window.tyreGridColumns = @json($grid_columns);
    </script>

    <script src="{{ asset('js/tyres-grid.js') }}?v={{ filemtime(public_path('js/tyres-grid.js')) }}"></script>
</x-filament-panels::page>
