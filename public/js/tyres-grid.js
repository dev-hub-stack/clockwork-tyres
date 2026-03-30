(function () {
    let tyresGrid;

    function buildGrid() {
        const data = Array.isArray(window.tyreGridData) ? window.tyreGridData : [];
        const colModel = Array.isArray(window.tyreGridColumns) ? window.tyreGridColumns : [];

        tyresGrid = pq.grid('#tyresGrid', {
            width: '100%',
            height: 620,
            wrap: false,
            hwrap: false,
            showTitle: false,
            showToolbar: false,
            numberCell: { show: true, title: '#' },
            sortable: true,
            filterModel: { on: true, mode: 'AND', header: true },
            menuIcon: false,
            hoverMode: 'cell',
            selectionModel: { type: 'cell' },
            editModel: { clicksToEdit: 2 },
            editable: false,
            stripeRows: true,
            resizable: true,
            pageModel: { type: 'local', rPP: 25, rPPOptions: [25, 50, 100] },
            dataModel: {
                location: 'local',
                sorting: 'local',
                data,
            },
            colModel,
        });
    }

    function bindActions() {
        const refreshButton = document.getElementById('refresh-grid');
        if (refreshButton) {
            refreshButton.addEventListener('click', function () {
                window.location.reload();
            });
        }
    }

    $(function () {
        buildGrid();
        bindActions();
    });
})();
