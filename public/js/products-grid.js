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
    
    // Column definitions - EXACT TUNERSTOP STRUCTURE
    var colModel = [
        // Action column with delete button
        { 
            title: "", 
            editable: false,
            skipExport: true, 
            minWidth: 85, 
            sortable: false, 
            align: "center",
            render: function (ui) {
                return "<button type='button' class='delete_btn icon-add'></button>";
            },
            postRender: function (ui) {
                var grid = this,
                    $cell = grid.getCell(ui);
                $cell.find(".delete_btn").bind("click", function (evt) {
                    grid.deleteRow({ rowIndx: ui.rowIndx });
                });
            }
        },
        // Checkbox column with "Select All"
        { 
            dataIndx: "state",
            align: "center",
            title: "<label><input type='checkbox' /></label>",
            cb: { header: true, select: true, all: true },
            type: 'checkbox',
            cls: 'ui-state-default', 
            dataType: 'bool',
            skipExport: true,
            editor: false,
            width: 10, 
            sortable: false
        },
        // Data columns
        {
            title: "SKU", 
            width: 160, 
            dataType: "string", 
            align: "center", 
            dataIndx: "sku", 
            validations: [{type: 'nonEmpty', msg: "SKU is required."}], 
            filter: { crules: [{ condition: 'begin' }] }  
        },
        {
            title: "Brand", 
            width: 130, 
            dataType: "string", 
            align: "center", 
            dataIndx: "brand", 
            validations: [{type: 'nonEmpty', msg: "Brand is required."}], 
            filter: { crules: [{ condition: 'equal' }] }
        },
        {
            title: "Model", 
            width: 160, 
            dataType: "string", 
            align: "center", 
            dataIndx: "model", 
            validations: [{type: 'nonEmpty', msg: "Model is required."}], 
            filter: { crules: [{ condition: 'equal' }] }  
        },
        {
            title: "Supplier Stock", 
            width: 120, 
            dataType: "integer", 
            align: "right", 
            dataIndx: "supplier_stock"
        },
        {
            title: "Finish", 
            width: 130, 
            dataType: "string", 
            align: "center", 
            dataIndx: "finish", 
            validations: [{type: 'nonEmpty', msg: "Finish is required."}], 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Construction", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "construction", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Rim Width", 
            width: 80, 
            dataType: "float", 
            align: "center", 
            dataIndx: "rim_width", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Rim Diameter", 
            width: 80, 
            dataType: "float", 
            align: "center", 
            dataIndx: "rim_diameter", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Size", 
            width: 80, 
            dataType: "string", 
            align: "center", 
            dataIndx: "size", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Bolt Pattern", 
            width: 100, 
            dataType: "string", 
            align: "center", 
            dataIndx: "bolt_pattern", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Hub Bore", 
            width: 80, 
            dataType: "float", 
            align: "center", 
            dataIndx: "hub_bore", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Offset", 
            width: 80, 
            dataType: "string", 
            align: "center", 
            dataIndx: "offset", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Warranty", 
            width: 90, 
            dataType: "string", 
            align: "center", 
            dataIndx: "backspacing", 
            filter: { crules: [{ condition: 'equal' }] } 
        },
        {
            title: "Max Wheel Load",
            width: 100, 
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
            dataIndx: "weight"
        },
        {
            title: "Lipsize", 
            width: 80, 
            dataType: "string", 
            align: "center", 
            dataIndx: "lipsize"
        },
        {
            title: "US Retail Price", 
            width: 80, 
            dataType: "float", 
            align: "center", 
            dataIndx: "us_retail_price"
        },
        {
            title: "UAE Retail Price", 
            width: 80, 
            dataType: "float", 
            align: "center", 
            dataIndx: "uae_retail_price"
        },
        {
            title: "Sale Price", 
            width: 80, 
            dataType: "float", 
            align: "center", 
            dataIndx: "sale_price"
        },
        {
            title: "Clearance Corner", 
            width: 100, 
            dataType: "integer", 
            align: "center", 
            type: "checkbox", 
            dataIndx: "clearance_corner", 
            cb: { check: 1, uncheck: 0 }
        },
        {
            title: "Images", 
            width: 200, 
            dataType: "string", 
            align: "center", 
            dataIndx: "images"
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
                label: 'Bulk Delete', 
                cls: 'voyager-delete', 
                listener: function () {
                    var ids = this.SelectRow().getSelection().map(function(rowList){
                        return rowList.rowData.id;
                    })
                    bulkDelete(ids);
                }
            }
        ]
    };
    
    // Main pqGrid configuration
    var obj = {
        rowHt: 50,
        rowBorders: true,
        trackModel: {on: true},
        height: '100vh',
        minHeight: '400px',
        maxHeight: $(window).height()-200,
        resizable: true,
        title: "<b>Products</b>",
        colModel: colModel,
        toolbar: toolbar,
        freezeCols: 2,
        filterModel: { header: true, type: 'local', on: true, mode: "AND" },
        
        // LOCAL DATA MODEL (not remote)
        dataModel: {
            dataType: "JSON",
            location: "local",
            data: data
        },
        
        // Pagination
        pageModel: {
            type: "local",
            rPP: 50,
            rPPOptions: [10, 20, 50, 100, 500]
        },
        
        // Scrolling
        scrollModel: { autoFit: true },
        
        // Selection
        selectionModel: { type: 'row', mode: 'block' },
        
        // Editing
        editable: true,
        editor: { select: true },
        clicksToEdit: 2
    };
    
    // Initialize grid
    grid = pq.grid("#productsGrid", obj);
    grid.refreshDataAndView();
    
    console.log('Products grid initialized successfully');
    
    // Optional: Auto-save interval (uncomment if needed)
    // interval = setInterval(saveChanges, 2000);
});
