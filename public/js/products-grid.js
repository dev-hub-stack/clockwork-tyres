/**
 * Products pqGrid Implementation - TUNERSTOP STRUCTURE
 * Matching: C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\products\data-grid.blade.php
 */

// Global variables
let grid;
let interval;

// Setup CSRF token for all AJAX requests
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

/**
 * Save changes to server
 */
function saveChanges() {
    if (grid.saveEditCell() === false) {
        return false;
    }
    
    if (!$.active && grid.isDirty() && grid.isValidChange({ allowInvalid: true }).valid) {
        var gridChanges = grid.getChanges({ format: 'byVal' });
        
        // Post changes to server
        $.ajax({
            dataType: "json",
            type: "POST",
            async: true,
            beforeSend: function (jqXHR, settings) {
                grid.option("strLoading", "Saving..");
                grid.showLoading();
            },
            url: "/admin/products/grid/save-batch",
            data: { list: gridChanges },
            success: function (changes) {
                grid.history({method: 'reset'});
                grid.commit({ type: 'add', rows: changes.addList });
                grid.commit({ type: 'update', rows: changes.updateList });
                grid.commit({ type: 'delete', rows: changes.deleteList });
            },
            complete: function (resp) {
                grid.hideLoading();
                grid.option("strLoading", $.paramquery.pqGrid.defaults.strLoading);
            },
            error: function (errors) {
                errorMessage = "";
                if (errors.responseJSON && errors.responseJSON.errors) {
                    errors.responseJSON.errors.forEach(function(element, index) {
                        errorMessage += element;
                    });
                    console.log(errorMessage);
                }
                clearInterval(interval);
            }
        });
    }
}

/**
 * Bulk delete selected rows
 */
function bulkDelete(ids) {
    if(ids.length <= 0){
        alert("Please select an Item to delete.");
        return false;
    }
    
    var gridChanges = grid.getChanges({ format: 'byVal' });
    
    $.ajax({
        dataType: "json",
        type: "POST",
        async: true,
        beforeSend: function (jqXHR, settings) {
            grid.option("strLoading", "Deleting....");
            grid.showLoading();
        },
        url: "/admin/products/grid/delete-batch",
        data: { list: gridChanges, deleteIds: ids },
        success: function (changes) {
            // Success
        },
        complete: function (resp) {
            if(resp['status'] == 200){
                window.location.reload();
            }
        },
        error: function (errors) {
            console.log(errors.responseJSON ? errors.responseJSON.errors : errors);
        }
    });
}

$(document).ready(function () {
    
    // Data is already loaded in page via: var data = @json($products_data);
    console.log('Grid data loaded:', data.length + ' rows');
    
    // Column definitions - MATCHING TUNERSTOP PROFESSIONAL LAYOUT
    var colModel = [
        // Checkbox column with "Select All"
        { 
            dataIndx: "state",
            align: "center",
            title: "<label><input type='checkbox' />&nbsp;Select All</label>",
            cb: { header: true, select: true, all: true },
            type: 'checkbox',
            cls: 'ui-state-default', 
            dataType: 'bool',
            skipExport: true,
            editor: false,
            width: 50, 
            sortable: false
        },
        // Action column with delete button
        { 
            title: "Action", 
            editable: false,
            skipExport: true, 
            minWidth: 100,
            width: 100,
            sortable: false, 
            align: "center",
            render: function (ui) {
                return "<button type='button' class='delete_btn'>Delete</button>";
            },
            postRender: function (ui) {
                var grid = this,
                    $cell = grid.getCell(ui);
                $cell.find(".delete_btn").bind("click", function (evt) {
                    grid.deleteRow({ rowIndx: ui.rowIndx });
                });
            }
        },
        // Data columns - WIDER FOR BETTER READABILITY
        {
            title: "SKU", 
            width: 200,         // Increased 
            dataType: "string", 
            align: "center", 
            dataIndx: "sku", 
            validations: [{type: 'nonEmpty', msg: "SKU is required."}], 
            filter: { crules: [{ condition: 'begin' }] }  
        },
        {
            title: "Brand", 
            width: 180,         // Increased
            dataType: "string", 
            align: "center", 
            dataIndx: "brand", 
            validations: [{type: 'nonEmpty', msg: "Brand is required."}], 
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "Model", 
            width: 200,         // Increased
            dataType: "string", 
            align: "center", 
            dataIndx: "model", 
            validations: [{type: 'nonEmpty', msg: "Model is required."}], 
            filter: { crules: [{ condition: 'equal' }] }  
        },
        {
            title: "Finish", 
            width: 180,         // Increased
            dataType: "string", 
            align: "center", 
            dataIndx: "finish", 
            validations: [{type: 'nonEmpty', msg: "Finish is required."}], 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Construction", 
            width: 150,         // Increased
            dataType: "string", 
            align: "center", 
            dataIndx: "construction", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Rim Width", 
            width: 120,         // Increased
            dataType: "float", 
            align: "center", 
            dataIndx: "rim_width", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Rim Diameter", 
            width: 130,         // Increased
            dataType: "float", 
            align: "center", 
            dataIndx: "rim_diameter", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Size", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "size", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Bolt Pattern", 
            width: 120, 
            dataType: "string", 
            align: "center", 
            dataIndx: "bolt_pattern", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Hub Bore", 
            width: 100, 
            dataType: "float", 
            align: "center", 
            dataIndx: "hub_bore", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Offset", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "offset", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Warranty", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "backspacing", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Max Wheel Load",
            width: 130, 
            dataType: "string", 
            align: "center", 
            dataIndx: "max_wheel_load", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Weight", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "weight",
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "Lipsize", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "lipsize",
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "US Retail Price", 
            width: 120, 
            dataType: "float", 
            align: "center", 
            dataIndx: "us_retail_price",
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "UAE Retail Price", 
            width: 130, 
            dataType: "float", 
            align: "center", 
            dataIndx: "uae_retail_price",
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "Sale Price", 
            width: 110, 
            dataType: "float", 
            align: "center", 
            dataIndx: "sale_price",
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "Images", 
            width: 250, 
            dataType: "string", 
            align: "center", 
            dataIndx: "images",
            filter: { crules: [{ condition: 'begin' }] }
        }
    ];
    
    // Toolbar configuration
    var toolbar = {
        cls: 'pq-toolbar-export',
        items: [
            {
                type: 'select',
                label: 'Format: ',
                attr: 'id="export_format"',
                options: [{xlsx: 'Excel', csv: 'Csv', htm: 'Html'}]
            },
            {
                type: 'button',
                label: " Export",
                cls: "voyager-Export",
                listener: function () {
                    var format = $("#export_format").val(),
                        blob = this.exportData({
                            format: format,
                            nopqdata: true,
                            render: true
                        });
                    if (typeof blob === "string") {
                        blob = new Blob([blob]);
                    }
                    saveAs(blob, "Product." + format);
                }
            },
            {type: 'separator'},
            {
                type: 'textbox',
                label: "Filter: ",
                attr: 'placeholder="Enter text"',
                listener: {
                    timeout: function (evt) {
                        var txt = $(evt.target).val();
                        var rules = this.getCMPrimary().map(function (colModel) {
                            return {
                                dataIndx: colModel.dataIndx,
                                condition: 'contain',
                                value: txt
                            }
                        })
                        this.filter({
                            mode: 'OR',
                            rules: rules
                        })
                    }
                }
            },
            {type: 'separator'},
            {
                type: 'button',
                label: '🔍 Toggle Filters',
                listener: function () {
                    var currentState = this.option('filterModel.header');
                    console.log('Current filter header state:', currentState);
                    this.option('filterModel.header', !currentState);
                    this.refreshHeader();
                    this.refresh();
                    console.log('New filter header state:', this.option('filterModel.header'));
                }
            },
            {type: 'separator'},
            {
                type: 'button', 
                icon: '', 
                label: ' New Product', 
                cls: 'voyager-plus', 
                listener: function () {
                    var rowData = {product_id: ''};
                    var rowIndx = this.addRow({rowData: rowData, checkEditable: true});
                    this.goToPage({rowIndx: rowIndx});
                    this.editFirstCellInRow({rowIndx: rowIndx});
                }
            },
            {type: 'separator'},
            {
                type: 'button',
                icon: '',
                label: 'Save Changes',
                cls: 'changes voyager-save grid-save-btn',
                listener: saveChanges,
                options: {disabled: true}
            },
            {type: 'separator'},
            {
                type: 'button', 
                icon: '', 
                label: 'Bulk Delete', 
                cls: 'voyager-delete', 
                listener: function () {
                    // Get all checked rows
                    var checkedRows = this.getColModel()[0]; // checkbox column
                    var allData = this.option('dataModel.data');
                    var ids = [];
                    
                    // Find all rows where checkbox is checked
                    for (var i = 0; i < allData.length; i++) {
                        if (allData[i].state === true || allData[i].state === 1) {
                            ids.push(allData[i].id);
                        }
                    }
                    
                    if (ids.length > 0) {
                        if (confirm('Are you sure you want to delete ' + ids.length + ' product(s)?')) {
                            bulkDelete(ids);
                        }
                    } else {
                        alert('Please select products to delete');
                    }
                }
            }
        ]
    };
    
    // Main pqGrid configuration - WIDER COLUMNS FOR READABILITY
    var obj = {
        width: '100%',          // Full width of container
        height: 'flex',
        minHeight: 500,
        maxHeight: $(window).height() - 150,
        rowHt: 50,
        rowBorders: true,
        trackModel: {on: true},
        resizable: true,
        title: "<b>Products</b>",
        colModel: colModel,
        toolbar: toolbar,
        freezeCols: 2,
        wrap: false,
        hwrap: false,
        hoverMode: 'cell',
        
        // Enable horizontal scrolling for wide columns (like Tunerstop)
        scrollModel: { 
            horizontal: true,       // Enable horizontal scroll
            autoFit: false,         // Don't auto-fit columns (allow horizontal scroll)
            pace: 'fast'            // Smooth scrolling
        },
        
        // FILTER MODEL - CRITICAL: Exactly like Tunerstop
        filterModel: { 
            header: true,           // Show filter row below column headers
            type: 'local',          // Local filtering
            on: true,               // Enable filtering
            mode: "AND",            // AND mode for multiple filters
            menuIcon: true          // Show filter menu icon
        },
        
        // LOCAL DATA MODEL (not remote)
        dataModel: {
            dataType: "JSON",
            location: "local",
            data: data,
            recIndx: "id"
        },
        
        // Pagination
        pageModel: {
            type: "local",
            rPP: 50,
            rPPOptions: [10, 20, 50, 100, 500]
        },
        
        // Selection
        selectionModel: { type: 'row', mode: 'block' },
        
        // Editing
        editable: true,
        editor: { select: true },
        clicksToEdit: 2,
        
        // Event handlers - CRITICAL for auto-save
        history: function (evt, ui) {
            var $tb = this.toolbar();
            if (ui.canUndo != null) {
                $("button.changes", $tb).button("option", {disabled: !ui.canUndo});
            }
            if (ui.canRedo != null) {
                $("button:contains('Redo')", $tb).button("option", "disabled", !ui.canRedo);
            }
            $("button:contains('Undo')", $tb).button("option", {label: 'Undo (' + ui.num_undo + ')'});
            $("button:contains('Redo')", $tb).button("option", {label: 'Redo (' + ui.num_redo + ')'});
        },
        
        change: function (evt, ui) {
            // Auto-save changes (add, update, delete) to server
            saveChanges();
        },
        
        destroy: function () {
            // Clear any intervals upon destroy
            if (typeof interval !== 'undefined') {
                clearInterval(interval);
            }
        },
        
        postRenderInterval: -1, // Call postRender synchronously
        
        load: function (evt, ui) {
            var grid = this,
            data = grid.option('dataModel').data;
            // Validate the whole data
            grid.isValid({ data: data });
        }
    };
    
    // Initialize grid with setTimeout like Tunerstop
    setTimeout(function () {
        console.log('🎯 Initializing pqGrid with professional layout...');
        console.log('📦 Data length:', data.length);
        
        grid = pq.grid("#productsGrid", obj);
        
        console.log('✅ Grid initialized');
        
        // Force filter header visibility after render
        setTimeout(function() {
            console.log('🔍 Verifying filter header...');
            
            // Force refresh to ensure filter row appears
            grid.refreshHeader();
            grid.refresh();
            
            // Log status
            var filterFields = $('.pq-grid-hd-search-field');
            console.log('🔍 Filter fields visible:', filterFields.length);
            
            if (filterFields.length > 0) {
                console.log('✅ Filter header is working!');
                // Add placeholder text
                filterFields.attr('placeholder', 'Filter...');
            } else {
                console.warn('⚠️ Filter fields not found. Checking configuration...');
                console.log('Filter model:', grid.option('filterModel'));
            }
        }, 300);
    }, 100);
    
    // Optional: Auto-save interval (uncomment if needed)
    // interval = setInterval(saveChanges, 2000);
});
