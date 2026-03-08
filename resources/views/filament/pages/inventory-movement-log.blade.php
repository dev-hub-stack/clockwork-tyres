<x-filament-panels::page>
<style>
    .fi-body, .fi-main, .fi-content, .fi-section-content-ctn { max-width: none !important; width: 100% !important; }
    .log-page { background: #fff; border-radius: 8px; padding: 20px; }
    .log-filters { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; align-items: flex-end; }
    .log-filters label { font-size: 0.75rem; font-weight: 600; color: #6b7280; display: block; margin-bottom: 4px; }
    .log-filters input, .log-filters select { padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; color: #111; }
    .log-filters .filter-group { display: flex; flex-direction: column; }
    .badge-action { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:0.7rem; font-weight:600; white-space:nowrap; }
    .badge-transfer_in  { background:#d1fae5; color:#065f46; }
    .badge-transfer_out { background:#fee2e2; color:#991b1b; }
    .badge-import       { background:#dbeafe; color:#1e40af; }
    .badge-adjustment   { background:#fef3c7; color:#92400e; }
    .badge-sale         { background:#ede9fe; color:#5b21b6; }
    .badge-return       { background:#fce7f3; color:#9d174d; }
    .qty-change-pos { color: #059669; font-weight: 700; }
    .qty-change-neg { color: #dc2626; font-weight: 700; }
    #log-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    #log-table th { background: #f3f4f6; padding: 8px 12px; text-align: left; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #e5e7eb; white-space: nowrap; }
    #log-table td { padding: 8px 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    #log-table tr:hover td { background: #f9fafb; }
</style>

<div class="log-page">
    <!-- Filters -->
    <div class="log-filters">
        <div class="filter-group">
            <label>SKU</label>
            <input type="text" id="f-sku" placeholder="Search SKU..." style="width:160px;">
        </div>
        <div class="filter-group">
            <label>Action</label>
            <select id="f-action" style="width:160px;">
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
            <select id="f-warehouse" style="width:160px;">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->code }} – {{ $wh->warehouse_name }}</option>
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
        <button onclick="loadLogs()" style="background:#4f46e5;color:white;border:none;padding:7px 20px;border-radius:6px;cursor:pointer;font-size:0.875rem;font-weight:500;align-self:flex-end;">
            🔍 Filter
        </button>
        <button onclick="clearFilters()" style="background:#e5e7eb;color:#374151;border:none;padding:7px 16px;border-radius:6px;cursor:pointer;font-size:0.875rem;font-weight:500;align-self:flex-end;">
            ✕ Clear
        </button>
    </div>

    <!-- Stats row -->
    <div id="log-stats" style="font-size:0.8rem;color:#6b7280;margin-bottom:12px;"></div>

    <!-- Table -->
    <div style="overflow-x:auto;">
        <table id="log-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date / Time</th>
                    <th>User</th>
                    <th>SKU</th>
                    <th>Action</th>
                    <th>Warehouse</th>
                    <th style="text-align:center;">Qty Before</th>
                    <th style="text-align:center;">Qty After</th>
                    <th style="text-align:center;">Change</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody id="log-tbody">
                <tr><td colspan="10" class="text-center" style="padding:2rem;color:#9ca3af;">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function loadLogs() {
    var params = {
        sku:          $('#f-sku').val(),
        action:       $('#f-action').val(),
        warehouse_id: $('#f-warehouse').val(),
        from_date:    $('#f-from-date').val(),
        to_date:      $('#f-to-date').val(),
    };

    $('#log-tbody').html('<tr><td colspan="10" style="padding:2rem;text-align:center;color:#9ca3af;">Loading...</td></tr>');

    $.ajax({
        url: '/admin/inventory/log-data',
        method: 'GET',
        data: params,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        success: function(logs) {
            var html = '';
            if (!logs.length) {
                html = '<tr><td colspan="10" style="padding:2rem;text-align:center;color:#9ca3af;">No records found.</td></tr>';
            } else {
                logs.forEach(function(log) {
                    var changeClass = log.quantity_change > 0 ? 'qty-change-pos' : (log.quantity_change < 0 ? 'qty-change-neg' : '');
                    var changePrefix = log.quantity_change > 0 ? '+' : '';
                    var actionBadge = '<span class="badge-action badge-' + log.action + '">' + log.action.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()) + '</span>';
                    html += '<tr>'
                        + '<td style="color:#9ca3af;font-size:0.7rem;">' + log.id + '</td>'
                        + '<td style="white-space:nowrap;">' + (log.created_at||'').replace('T',' ').substring(0,16) + '</td>'
                        + '<td>' + (log.user_name || '<span style="color:#9ca3af;">System</span>') + '</td>'
                        + '<td style="font-weight:600;">' + (log.sku || '–') + '</td>'
                        + '<td>' + actionBadge + '</td>'
                        + '<td>' + (log.warehouse_code ? log.warehouse_code + ' <span style="color:#9ca3af;font-size:0.7rem;">'+log.warehouse_name+'</span>' : '–') + '</td>'
                        + '<td style="text-align:center;">' + (log.quantity_before ?? '–') + '</td>'
                        + '<td style="text-align:center;">' + (log.quantity_after ?? '–') + '</td>'
                        + '<td style="text-align:center;" class="' + changeClass + '">' + changePrefix + (log.quantity_change ?? 0) + '</td>'
                        + '<td style="color:#6b7280;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + (log.notes||'').replace(/"/g,'&quot;') + '">' + (log.notes || '–') + '</td>'
                        + '</tr>';
                });
            }
            $('#log-tbody').html(html);
            $('#log-stats').text('Showing ' + logs.length + ' record(s)' + (logs.length === 500 ? ' (limited to 500 — use filters to narrow down)' : ''));
        },
        error: function() {
            $('#log-tbody').html('<tr><td colspan="10" style="color:#dc2626;padding:2rem;text-align:center;">Failed to load log data.</td></tr>');
        }
    });
}

function clearFilters() {
    $('#f-sku, #f-from-date, #f-to-date').val('');
    $('#f-action, #f-warehouse').val('');
    loadLogs();
}

$(document).ready(function() {
    loadLogs();
    // Live search on SKU with debounce
    var t;
    $('#f-sku').on('input', function() { clearTimeout(t); t = setTimeout(loadLogs, 400); });
    $('#f-action, #f-warehouse').on('change', loadLogs);
});
</script>
</x-filament-panels::page>
