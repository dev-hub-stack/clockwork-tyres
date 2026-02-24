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
    
    // Check if there are any changes to save
    if (grid.isDirty()) {
        var gridChanges = grid.getChanges({ format: 'byVal' });
        
        console.log('🔄 Saving changes:', gridChanges);
        
        // Only proceed if there are actual changes
        if (!gridChanges || (!gridChanges.updateList || gridChanges.updateList.length === 0) && 
            (!gridChanges.addList || gridChanges.addList.length === 0) && 
            (!gridChanges.deleteList || gridChanges.deleteList.length === 0)) {
            console.log('ℹ️ No actual changes to save');
            return false;
        }
        
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
                console.log('✅ Save successful:', changes);
                
                // Show success message
                if (changes.errors && changes.errors.length > 0) {
                    alert('Some changes had errors:\n' + changes.errors.join('\n'));
                } else {
                    // Show brief success indicator
                    grid.option("strLoading", "Saved!");
                    setTimeout(() => {
                        grid.option("strLoading", $.paramquery.pqGrid.defaults.strLoading);
                    }, 1000);
                    
                    // Update grid data locally instead of page reload
                    if (changes.updateList && changes.updateList.length > 0) {
                        console.log('🔄 Attempting to update grid locally...');
                        
                        // Method 1: Try to update local data first
                        var dataModel = grid.option('dataModel');
                        var data = dataModel.data;
                        var updatedCount = 0;
                        
                        changes.updateList.forEach(function(updatedRow) {
                            // Find the row in the grid data and update it
                            for (var i = 0; i < data.length; i++) {
                                if (data[i].id == updatedRow.id) {
                                    console.log('🔄 Updating row', i, 'with new data:', updatedRow);
                                    // Update the row data with new values
                                    Object.assign(data[i], updatedRow);
                                    console.log('✅ Row updated:', data[i]);
                                    updatedCount++;
                                    break;
                                }
                            }
                        });
                        
                        console.log('📊 Updated', updatedCount, 'rows in grid');
                        
                        // Try to refresh the grid
                        try {
                            grid.refreshDataAndView();
                            console.log('🔄 Grid refreshed');
                            
                            // Check if the update worked by verifying a specific cell
                            setTimeout(function() {
                                var testRow = changes.updateList[0];
                                var rowIndx = grid.getRowIndx({ rowData: testRow });
                                var cellValue = grid.getCellData({ rowIndx: rowIndx, dataIndx: 'uae_retail_price' });
                                
                                console.log('🔍 Verification - Expected:', testRow.uae_retail_price, 'Actual:', cellValue);
                                
                                if (cellValue != testRow.uae_retail_price) {
                                    console.log('❌ Local update failed, forcing cell update...');
                                    
                                    // Force update the specific cell
                                    changes.updateList.forEach(function(updatedRow) {
                                        var rowIndx = grid.getRowIndx({ rowData: updatedRow });
                                        if (rowIndx !== undefined) {
                                            // Update UAE Retail Price
                                            grid.updateCell({
                                                rowIndx: rowIndx,
                                                dataIndx: 'uae_retail_price',
                                                newVal: updatedRow.uae_retail_price
                                            });
                                            console.log('🔄 Forced cell update for uae_retail_price:', updatedRow.uae_retail_price);
                                            
                                            // Update Sale Price
                                            grid.updateCell({
                                                rowIndx: rowIndx,
                                                dataIndx: 'sale_price',
                                                newVal: updatedRow.sale_price
                                            });
                                            console.log('🔄 Forced cell update for sale_price:', updatedRow.sale_price);
                                            
                                            // Update the underlying data as well
                                            var dataModel = grid.option('dataModel');
                                            var data = dataModel.data;
                                            for (var i = 0; i < data.length; i++) {
                                                if (data[i].id == updatedRow.id) {
                                                    data[i].uae_retail_price = updatedRow.uae_retail_price;
                                                    data[i].sale_price = updatedRow.sale_price;
                                                    console.log('✅ Updated underlying data for row', i);
                                                    break;
                                                }
                                            }
                                            
                                            // Refresh the grid
                                            setTimeout(function() {
                                                grid.refresh();
                                                console.log('🔄 Grid refreshed after forced cell update');
                                                
                                                // Verify again
                                                setTimeout(function() {
                                                    var newCellValue = grid.getCellData({ rowIndx: rowIndx, dataIndx: 'uae_retail_price' });
                                                    console.log('🔍 Final verification - Expected:', testRow.uae_retail_price, 'Actual:', newCellValue);
                                                    
                                                    if (newCellValue == testRow.uae_retail_price) {
                                                        console.log('✅ Cell update successful!');
                                                    } else {
                                                        console.log('❌ Cell update still failed, fetching fresh data...');
                                                        fetchFreshData();
                                                    }
                                                }, 200);
                                            }, 100);
                                        }
                                    });
                                } else {
                                    console.log('✅ Local update successful!');
                                }
                            }, 500);
                            
                        } catch (error) {
                            console.error('❌ Grid update error:', error);
                            fetchFreshData();
                        }
                    }
                    
                    // Function to fetch fresh data from server
                    function fetchFreshData() {
                        console.log('🌐 Fetching fresh data from server...');
                        
                        $.ajax({
                            url: '/admin/products-grid',
                            method: 'GET',
                            dataType: 'html',
                            success: function(html) {
                                console.log('✅ Fresh data fetched, updating grid...');
                                // Extract the data from the HTML response
                                var dataMatch = html.match(/var data = (\[.*?\]);/);
                                if (dataMatch) {
                                    try {
                                        var freshData = JSON.parse(dataMatch[1]);
                                        console.log('� Fresh data loaded:', freshData.length, 'rows');
                                        
                                        // Update the grid with fresh data
                                        var dataModel = grid.option('dataModel');
                                        dataModel.data = freshData;
                                        grid.refreshDataAndView();
                                        console.log('✅ Grid updated with fresh data!');
                                    } catch (e) {
                                        console.error('❌ Error parsing fresh data:', e);
                                        location.reload();
                                    }
                                } else {
                                    console.error('❌ Could not extract data from response');
                                    location.reload();
                                }
                            },
                            error: function() {
                                console.error('❌ Failed to fetch fresh data');
                                location.reload();
                            }
                        });
                    }
                }
                
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
                console.error('❌ Save failed:', errors);
                
                errorMessage = "";
                if (errors.responseJSON && errors.responseJSON.errors) {
                    errors.responseJSON.errors.forEach(function(element, index) {
                        errorMessage += element + "\n";
                    });
                    alert('Save failed:\n' + errorMessage);
                } else if (errors.responseJSON && errors.responseJSON.message) {
                    alert('Save failed:\n' + errors.responseJSON.message);
                } else {
                    alert('Save failed. Please check console for details.');
                }
                
                clearInterval(interval);
            }
        });
    } else {
        console.log('ℹ️ No changes to save');
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
            editable: false,              // Can't edit images in grid
            filter: false                 // NO FILTER for images column
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
    
    // Main pqGrid configuration - EXACTLY LIKE TUNERSTOP
    var obj = {
        width: '100%',          // Full width of container
        height: '100vh',        // Full viewport height like Tunerstop
        minHeight: 500,
        rowHt: 50,              // Row height
        rowBorders: true,       // Show row borders
        trackModel: {on: true}, // Track changes
        resizable: true,        // Resizable columns
        title: "<b>Products</b>",
        colModel: colModel,
        toolbar: toolbar,
        freezeCols: 2,          // Freeze first 2 columns
        wrap: false,
        hwrap: false,
        swipeModel: {on: false}, // Disable swipe
        
        // Enable horizontal scrolling for wide columns
        scrollModel: { 
            horizontal: true,       // Enable horizontal scroll
            autoFit: false,         // Don't auto-fit columns
            pace: 'fast'            // Smooth scrolling
        },
        
        // FILTER MODEL - CRITICAL
        filterModel: { 
            header: true,           // Show filter row below column headers
            type: 'local',          // Local filtering
            on: true,               // Enable filtering
            mode: "AND"             // AND mode for multiple filters
            // NO menuIcon property = no arrows
        },
        
        // LOCAL DATA MODEL (not remote)
        dataModel: {
            dataType: "JSON",
            recIndx: "id",          // CRITICAL: Must match Tunerstop
            data: data
        },
        
        // Pagination - EXACTLY LIKE TUNERSTOP
        pageModel: { 
            type: "local", 
            rPP: 100, 
            option: [100, 200, 300, 400, 500]  // CRITICAL: 'option' not 'rPPOptions'
        },
        
        // Selection Model - Enable Excel-like behavior
        selectionModel: { 
            type: 'cell',           // CRITICAL: Change from 'row' to 'cell' for Excel-like
            mode: 'block'           // Allow block selection
        },
        
        // Editing - Enable Excel-like editing with fill handle
        editable: true,             // Make grid editable
        editor: { 
            select: true,           // Select text on edit
            autoFocus: true         // Auto focus when editing
        },
        editModel: {
            clicksToEdit: 2,        // Double-click to edit (like Excel)
            saveKey: $.ui.keyCode.ENTER,
            keyUpDown: true         // Arrow keys navigate during edit
        },
        
        // FILL HANDLE - Excel-like drag down feature
        fillHandle: 'all',          // Enable fill handle on all cells
        
        // Copy/Paste - Excel-like functionality  
        copyModel: {
            on: true,               // Enable copy/paste
            render: true            // Copy rendered values
        },
        
        postRenderInterval: -1,     // Call postRender synchronously
        
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
            console.log('🔄 Change detected:', ui);
            // Auto-save changes (add, update, delete) to server
            saveChanges();
        },
        
        // Add cellEdit event for single cell changes
        cellEdit: function (evt, ui) {
            console.log('✏️ Cell edited:', ui);
            // Save immediately after cell edit
            setTimeout(() => {
                saveChanges();
            }, 500);
        },
        
        // Add editorStop event for when user finishes editing
        editorStop: function (evt, ui) {
            console.log('📝 Editor stopped:', ui);
            // Save when user stops editing (presses Enter or clicks away)
            setTimeout(() => {
                saveChanges();
            }, 200);
        },
        
        destroy: function () {
            // Clear any intervals upon destroy
            if (typeof interval !== 'undefined') {
                clearInterval(interval);
            }
        },
        
        load: function (evt, ui) {
            var grid = this,
                data = grid.option('dataModel').data;
            
            // Attach tooltip like Tunerstop
            grid.widget().pqTooltip();
            
            // Validate the whole data
            grid.isValid({ data: data });
        }
    };
    
    // Initialize grid with setTimeout like Tunerstop
    setTimeout(function () {
        console.log('🎯 Initializing pqGrid with professional layout...');
        console.log('📦 Data length:', data.length);
        console.log('📋 Sample data row:', data[0]);
        
        grid = pq.grid("#productsGrid", obj);
        
        console.log('✅ Grid initialized');
        console.log('📄 Page Model:', grid.option('pageModel'));
        console.log('🔍 Filter Model:', grid.option('filterModel'));
        
        // Force filter header visibility after render
        setTimeout(function() {
            console.log('🔍 Verifying filter header and pagination...');
            
            // Force refresh to ensure filter row appears
            grid.refreshHeader();
            grid.refresh();
            
            // Log status
            var filterFields = $('.pq-grid-hd-search-field');
            var pager = $('.pq-pager');
            
            console.log('🔍 Filter fields visible:', filterFields.length);
            console.log('📄 Pagination container:', pager.length);
            console.log('📊 Total pages:', grid.option('pageModel.curPage'), 'of', Math.ceil(data.length / grid.option('pageModel.rPP')));
            
            if (filterFields.length > 0) {
                console.log('✅ Filter header is working!');
                // Add placeholder text
                filterFields.attr('placeholder', 'Filter...');
            } else {
                console.warn('⚠️ Filter fields not found. Checking configuration...');
                console.log('Filter model:', grid.option('filterModel'));
            }
            
            if (pager.length > 0) {
                console.log('✅ Pagination is rendering!');
            } else {
                console.warn('⚠️ Pagination not visible. Checking pageModel...');
            }
        }, 300);
    }, 100);
    
    // Optional: Auto-save interval (uncomment if needed)
    // interval = setInterval(saveChanges, 2000);
});
