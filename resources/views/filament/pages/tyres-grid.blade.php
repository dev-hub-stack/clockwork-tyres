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
                        This tyre admin page now follows the same CRM grid layout language as the existing product and inventory grids. The importer now stages XLSX and CSV supplier files, then previews the latest account-scoped rows here before we write them into the live tyre catalogue.
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

        @if (session('tyre_import_status'))
            <div class="alert alert-success mb-0" role="alert">
                <strong><i class="bi bi-check-circle"></i> Import staged:</strong>
                {{ session('tyre_import_status') }}
            </div>
        @endif

        @if ($errors->has('import_file'))
            <div class="alert alert-danger mb-0" role="alert">
                <strong><i class="bi bi-exclamation-triangle"></i> Import error:</strong>
                {{ $errors->first('import_file') }}
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-[1.5fr,1fr]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Tyre import staging</h2>
                        <p class="text-sm text-slate-600">
                            Upload an XLSX or CSV file for the active account. The file is validated, grouped by George’s merge rule, and staged here before any live catalogue write happens.
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active account</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $current_account_summary['name'] ?? 'No active account' }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $current_account_summary['slug'] ?? 'Select an active business account to stage an import.' }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Latest staged batch</p>
                            @if (!empty($latest_import_batch))
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ $latest_import_batch['file_name'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $latest_import_batch['source_format'] }} · {{ $latest_import_batch['status'] }} · {{ $latest_import_batch['uploaded_at'] }}
                                </p>
                            @else
                                <p class="mt-1 text-sm font-semibold text-slate-900">No staged tyre file yet</p>
                                <p class="text-xs text-slate-500">Until the first supplier file is staged, the grid stays on the launch-contract placeholder row.</p>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.tyre-grid.import') }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1fr,auto] md:items-end">
                        @csrf
                        <div>
                            <label for="import_file" class="mb-2 block text-sm font-medium text-slate-700">Supplier tyre file</label>
                            <input
                                id="import_file"
                                name="import_file"
                                type="file"
                                accept=".xlsx,.csv"
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            >
                            <p class="mt-2 text-xs text-slate-500">Accepted formats: XLSX and CSV. Maximum upload size: 10 MB.</p>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-upload"></i> Stage Import
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Import status</h2>
                        <p class="text-sm text-slate-600">
                            These counters come from the latest staged file for the active account.
                        </p>
                    </div>

                    @if (!empty($import_summary_cards))
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($import_summary_cards as $card)
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $card['value'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $card['note'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                            Stage the first tyre file to see batch counts, duplicates, and invalid rows here.
                        </div>
                    @endif
                </div>
            </div>
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

        @if (!empty($import_issue_rows))
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3">
                    <h2 class="text-lg font-semibold text-slate-900">Import issues and warnings</h2>
                    <p class="text-sm text-slate-600">
                        These rows need review before the batch can move into the live tyre catalogue.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Row</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Status</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">SKU</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-700">Summary</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($import_issue_rows as $issue)
                                <tr>
                                    <td class="px-3 py-2 text-slate-700">{{ $issue['source_row_number'] }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold uppercase tracking-wide {{ $issue['status'] === 'duplicate' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ $issue['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ $issue['sku'] }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ $issue['summary'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
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
