<x-filament-panels::page>
<style>
    .fi-body, .fi-main, .fi-content, .fi-section-content-ctn { max-width: none !important; width: 100% !important; }
    .log-page { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04); }
    .log-tabs { display: flex; gap: 10px; margin-bottom: 18px; flex-wrap: wrap; }
    .log-tab-btn { border: 1px solid #d1d5db; background: #fff; color: #111827; padding: 8px 16px; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
    .log-tab-btn.active { background: #111827; color: #fff; border-color: #111827; }
    .log-filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; align-items: flex-end; }
    .log-filters label { font-size: 0.75rem; font-weight: 700; color: #6b7280; display: block; margin-bottom: 6px; letter-spacing: .08em; text-transform: uppercase; }
    .log-filters input, .log-filters select { padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 12px; font-size: 0.875rem; color: #111; min-height: 44px; }
    .log-filters .filter-group { display: flex; flex-direction: column; }
    .badge-type, .badge-action { display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 0.68rem; font-weight: 700; white-space: nowrap; letter-spacing: .05em; text-transform: uppercase; }
    .badge-type-products { background: #dbeafe; color: #1d4ed8; }
    .badge-type-tyres { background: #dcfce7; color: #166534; }
    .badge-type-addons { background: #f3e8ff; color: #7c3aed; }
    .badge-transfer_in  { background: #d1fae5; color: #065f46; }
    .badge-transfer_out { background: #fee2e2; color: #991b1b; }
    .badge-import       { background: #dbeafe; color: #1e40af; }
    .badge-adjustment   { background: #fef3c7; color: #92400e; }
    .badge-sale         { background: #ede9fe; color: #5b21b6; }
    .badge-return       { background: #fce7f3; color: #9d174d; }
    .qty-change-pos { color: #059669; font-weight: 700; }
    .qty-change-neg { color: #dc2626; font-weight: 700; }
    #log-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    #log-table th { background: #f3f4f6; padding: 10px 12px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #e5e7eb; white-space: nowrap; letter-spacing: .05em; }
    #log-table td { padding: 10px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    #log-table tr:hover td { background: #f9fafb; }
    .log-toolbar-btn { border: none; padding: 10px 18px; border-radius: 12px; cursor: pointer; font-size: 0.875rem; font-weight: 600; min-height: 44px; }
    .log-toolbar-btn-primary { background: #4f46e5; color: #fff; }
    .log-toolbar-btn-muted { background: #e5e7eb; color: #374151; }
</style>

<div class="log-page">
    <div class="log-tabs">
        <button type="button" class="log-tab-btn {{ ($defaultInventoryType ?? '') === '' ? 'active' : '' }}" data-type="">All</button>
        <button type="button" class="log-tab-btn {{ ($defaultInventoryType ?? '') === 'products' ? 'active' : '' }}" data-type="products">Products</button>
        <button type="button" class="log-tab-btn {{ ($defaultInventoryType ?? '') === 'tyres' ? 'active' : '' }}" data-type="tyres">Tyres</button>
        <button type="button" class="log-tab-btn {{ ($defaultInventoryType ?? '') === 'addons' ? 'active' : '' }}" data-type="addons">Addons</button>
    </div>

    <div class="log-filters">
        <input type="hidden" id="f-inventory-type" value="{{ $defaultInventoryType ?? '' }}">
        <div class="filter-group">
            <label>SKU</label>
            <input type="text" id="f-sku" placeholder="Search SKU..." style="width: 180px;">
        </div>
        <div class="filter-group">
            <label>Action</label>
            <select id="f-action" style="width: 170px;">
                <option value="">All Actions</option>
                <option value="transfer_in">Transfer In</option>
                <option value="transfer_out">Transfer Out</option>
                <option value="import">Import / Add</option>
                <option value="adjustment">Manual Adjustment</option>
                <option value="sale">Sale</option>
                <option value="return">Return</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Warehouse</label>
            <select id="f-warehouse" style="width: 190px;">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->code }} - {{ $wh->warehouse_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>From Date</label>
            <input type="date" id="f-from-date">
        </div>
        <div class="filter-group">
            <label>To Date</label>
            <input type="date" id="f-to-date">
        </div>
        <button type="button" onclick="loadLogs()" class="log-toolbar-btn log-toolbar-btn-primary">Filter</button>
        <button type="button" onclick="clearFilters()" class="log-toolbar-btn log-toolbar-btn-muted">Clear</button>
    </div>

    <div id="log-stats" style="font-size: 0.8rem; color: #6b7280; margin-bottom: 14px;"></div>

    <div style="overflow-x: auto;">
        <table id="log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date / Time</th>
                    <th>User</th>
                    <th>SKU</th>
                    <th>Type</th>
                    <th>Action</th>
                    <th>Warehouse</th>
                    <th style="text-align: center;">Qty Before</th>
                    <th style="text-align: center;">Qty After</th>
                    <th style="text-align: center;">Change</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody id="log-tbody">
                <tr><td colspan="11" style="padding: 2rem; text-align: center; color: #9ca3af;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadLogs() {
    var params = {
        sku: $('#f-sku').val(),
        inventory_type: $('#f-inventory-type').val(),
        action: $('#f-action').val(),
        warehouse_id: $('#f-warehouse').val(),
        from_date: $('#f-from-date').val(),
        to_date: $('#f-to-date').val(),
    };

    $('#log-tbody').html('<tr><td colspan="11" style="padding:2rem;text-align:center;color:#9ca3af;">Loading...</td></tr>');

    $.ajax({
        url: '/admin/inventory/log-data',
        method: 'GET',
        data: params,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(logs) {
            var html = '';

            if (!logs.length) {
                html = '<tr><td colspan="11" style="padding:2rem;text-align:center;color:#9ca3af;">No records found.</td></tr>';
            } else {
                logs.forEach(function(log) {
                    var changeClass = log.quantity_change > 0 ? 'qty-change-pos' : (log.quantity_change < 0 ? 'qty-change-neg' : '');
                    var changePrefix = log.quantity_change > 0 ? '+' : '';
                    var actionBadge = '<span class="badge-action badge-' + log.action + '">' + log.action.replace(/_/g, ' ').replace(/\\b\\w/g, function(c) { return c.toUpperCase(); }) + '</span>';
                    var type = log.inventory_type || 'products';
                    var typeBadge = '<span class="badge-type badge-type-' + type + '">' + type + '</span>';
                    var warehouseLabel = log.warehouse_code
                        ? log.warehouse_code + ' <span style="color:#9ca3af;font-size:0.7rem;">' + log.warehouse_name + '</span>'
                        : '-';

                    html += '<tr>'
                        + '<td style="color:#9ca3af;font-size:0.7rem;">' + log.id + '</td>'
                        + '<td style="white-space:nowrap;">' + (log.created_at || '').replace('T', ' ').substring(0, 16) + '</td>'
                        + '<td>' + (log.user_name || '<span style="color:#9ca3af;">System</span>') + '</td>'
                        + '<td style="font-weight:600;">' + (log.sku || '-') + '</td>'
                        + '<td>' + typeBadge + '</td>'
                        + '<td>' + actionBadge + '</td>'
                        + '<td>' + warehouseLabel + '</td>'
                        + '<td style="text-align:center;">' + (log.quantity_before ?? '-') + '</td>'
                        + '<td style="text-align:center;">' + (log.quantity_after ?? '-') + '</td>'
                        + '<td style="text-align:center;" class="' + changeClass + '">' + changePrefix + (log.quantity_change ?? 0) + '</td>'
                        + '<td style="color:#6b7280;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + (log.notes || '').replace(/"/g, '&quot;') + '">' + (log.notes || '-') + '</td>'
                        + '</tr>';
                });
            }

            $('#log-tbody').html(html);
            $('#log-stats').text('Showing ' + logs.length + ' record(s)' + (logs.length === 500 ? ' (limited to 500 — use filters to narrow down)' : ''));
        },
        error: function() {
            $('#log-tbody').html('<tr><td colspan="11" style="color:#dc2626;padding:2rem;text-align:center;">Failed to load log data.</td></tr>');
        }
    });
}

function clearFilters() {
    $('#f-sku, #f-from-date, #f-to-date').val('');
    $('#f-inventory-type').val('');
    $('#f-action, #f-warehouse').val('');
    $('.log-tab-btn').removeClass('active');
    $('.log-tab-btn[data-type=""]').addClass('active');
    loadLogs();
}

$(document).ready(function() {
    $('.log-tab-btn').removeClass('active');
    $('.log-tab-btn[data-type="' + ($('#f-inventory-type').val() || '') + '"]').addClass('active');
    loadLogs();

    var debounceHandle;
    $('#f-sku').on('input', function() {
        clearTimeout(debounceHandle);
        debounceHandle = setTimeout(loadLogs, 400);
    });

    $('#f-action, #f-warehouse').on('change', loadLogs);

    $('.log-tab-btn').on('click', function() {
        $('.log-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('#f-inventory-type').val($(this).data('type') || '');
        loadLogs();
    });
});
</script>
</x-filament-panels::page>
