<x-filament-panels::page>
    <div>
        <style>
            .stat-card {
                background: linear-gradient(135deg, var(--bg-color) 0%, var(--bg-dark) 100%);
                border-radius: 0.5rem;
                padding: 1.5rem;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                color: white;
                margin-bottom: 1rem;
            }
            .stat-card h2 { font-size: 2rem; font-weight: bold; margin: 0.5rem 0; }
            .stat-card p { font-size: 0.875rem; opacity: 0.9; margin: 0; }
            .card-blue { --bg-color: #3b82f6; --bg-dark: #2563eb; }
            .card-green { --bg-color: #10b981; --bg-dark: #059669; }
            .card-red { --bg-color: #ef4444; --bg-dark: #dc2626; }
            .card-orange { --bg-color: #f97316; --bg-dark: #ea580c; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
            .order-table { width: 100%; background: white; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .table-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
            .table-header h3 { margin: 0; font-size: 1.125rem; font-weight: 600; }
            .btn-refresh { background: #ec4899; color: white; padding: 0.5rem 1rem; border-radius: 0.375rem; border: none; cursor: pointer; }
            .btn-refresh:hover { background: #db2777; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
            th { background: #f9fafb; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #6b7280; border-bottom: 2px solid #e5e7eb; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            td { padding: 0.5rem 0.75rem; border-top: 1px solid #e5e7eb; font-size: 0.8rem; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; }
            tr:hover { background: #f9fafb; }
            .col-expand { width: 3%; text-align: center; padding: 0.5rem 0.25rem !important; }
            .col-date { width: 7%; }
            .col-order { width: 13%; }
            .col-customer { width: 13%; }
            .col-brand { width: 13%; }
            .col-vehicle { width: 13%; }
            .col-tracking { width: 14%; text-align: center; word-break: break-all; white-space: normal; overflow: visible !important; text-overflow: unset !important; }
            .col-status  { width: 10%; text-align: center; }
            .col-payment { width: 10%; text-align: center; }
            .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
            .badge-blue { background: #dbeafe; color: #1e40af; }
            .badge-green { background: #d1fae5; color: #065f46; }
            .badge-yellow { background: #fef3c7; color: #92400e; }
            .badge-red { background: #fee2e2; color: #991b1b; }
            .link-pink { color: #ec4899; font-weight: 500; text-decoration: none; }
            .link-pink:hover { color: #db2777; text-decoration: underline; }
            .action-btn { background: none; border: none; cursor: pointer; padding: 0.5rem; margin: 0 0.15rem; transition: all 0.2s; font-size: 1.25rem; border-radius: 0.25rem; }
            .action-btn:hover { transform: scale(1.15); background: rgba(0,0,0,0.05); }
            .action-btn svg { width: 1.25rem; height: 1.25rem; }
            .action-btn-blue { color: #3b82f6; }
            .action-btn-blue:hover { color: #2563eb; background: #dbeafe; }
            .action-btn-pink { color: #ec4899; }
            .action-btn-pink:hover { color: #db2777; background: #fce7f3; }
            .action-btn-yellow { color: #f59e0b; }
            .action-btn-yellow:hover { color: #d97706; background: #fef3c7; }
            .action-btn-green { color: #10b981; }
            .action-btn-green:hover { color: #059669; background: #d1fae5; }
            .expand-btn { background: none; border: none; cursor: pointer; padding: 0.25rem; font-size: 0.875rem; color: #6b7280; transition: transform 0.3s; }
            .expand-btn:hover { color: #374151; }
            [x-cloak] { display: none !important; }
        </style>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card card-blue">
                <p>Pending Orders</p>
                <h2>{{ $pendingOrders }}</h2>
            </div>
            <div class="stat-card card-green">
                <p>Monthly Revenue</p>
                <h2>{{ $currency }} {{ number_format($monthlyRevenue, 2) }}</h2>
            </div>
            <div class="stat-card card-red">
                <p>Today's Orders</p>
                <h2>{{ $todayOrders }}</h2>
            </div>
            <div class="stat-card card-orange">
                <p>Pending Warranty Claims</p>
                <h2>{{ $notifications }}</h2>
            </div>
        </div>

        <!-- Order Sheet Table -->
        <div class="order-table" x-data="orderSheet()">
            <div class="table-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
                <h3>Pending Orders - Order Sheet</h3>
                <!-- Tabs -->
                <div style="display:flex; gap:0.5rem;">
                    <button @click="activeTab='delivery'"
                        :style="activeTab==='delivery' ? 'background:#3b82f6;color:white;' : 'background:#e5e7eb;color:#374151;'"
                        style="padding:0.4rem 1rem; border:none; border-radius:9999px; cursor:pointer; font-size:0.8rem; font-weight:600; transition:all 0.2s;">
                        🚚 Pending Delivery
                        <span style="margin-left:0.4rem; background:rgba(0,0,0,0.12); border-radius:9999px; padding:0.1rem 0.5rem; font-size:0.7rem;"
                            x-text="pendingDeliveryOrders.length"></span>
                    </button>
                    <button @click="activeTab='payment'"
                        :style="activeTab==='payment' ? 'background:#f59e0b;color:white;' : 'background:#e5e7eb;color:#374151;'"
                        style="padding:0.4rem 1rem; border:none; border-radius:9999px; cursor:pointer; font-size:0.8rem; font-weight:600; transition:all 0.2s;">
                        💰 Pending Payment
                        <span style="margin-left:0.4rem; background:rgba(0,0,0,0.12); border-radius:9999px; padding:0.1rem 0.5rem; font-size:0.7rem;"
                            x-text="pendingPaymentOrders.length"></span>
                    </button>
                    <button @click="activeTab='all'"
                        :style="activeTab==='all' ? 'background:#6b7280;color:white;' : 'background:#e5e7eb;color:#374151;'"
                        style="padding:0.4rem 1rem; border:none; border-radius:9999px; cursor:pointer; font-size:0.8rem; font-weight:600; transition:all 0.2s;">
                        📋 View All
                        <span style="margin-left:0.4rem; background:rgba(0,0,0,0.12); border-radius:9999px; padding:0.1rem 0.5rem; font-size:0.7rem;"
                            x-text="orders.length"></span>
                    </button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-expand"></th>
                        <th class="col-date">Date</th>
                        <th class="col-order">Order #</th>
                        <th class="col-customer">Customer</th>
                        <th class="col-brand">Wheel Brand</th>
                        <th class="col-vehicle">Vehicle</th>
                        <th class="col-tracking">Tracking</th>
                        <th class="col-status">Status</th>
                        <th class="col-payment">Payment</th>
                    </tr>
                </thead>
                <template x-for="(order, index) in filteredOrders" :key="order.id">
                    <tbody>
                        <!-- Main Order Row -->
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td class="col-expand">
                                <button @click="toggleExpand(index)" class="expand-btn" 
                                        :style="expandedRows[index] ? 'transform: rotate(90deg);' : ''">
                                    ▶
                                </button>
                            </td>
                            <td class="col-date" x-text="order.created_at"></td>
                            <td class="col-order">
                                <a :href="order.order_url" class="link-pink" x-text="order.order_number"></a>
                            </td>
                            <td class="col-customer">
                                <a :href="order.customer_url" class="link-pink" x-text="order.customer_name"></a>
                            </td>
                            <td class="col-brand" x-text="order.wheel_brand"></td>
                            <td class="col-vehicle" x-text="order.vehicle"></td>
                            <td class="col-tracking">
                                <template x-if="order.tracking_url">
                                    <a :href="order.tracking_url" target="_blank" rel="noopener" class="badge badge-green" x-text="order.tracking_number || 'PENDING'" style="text-decoration:none; word-break:break-all; white-space:normal;"></a>
                                </template>
                                <template x-if="!order.tracking_url">
                                    <span class="badge badge-blue" x-text="order.tracking_number || 'PENDING'" style="word-break:break-all; white-space:normal;"></span>
                                </template>
                            </td>
                            <td class="col-status">
                                <template x-if="order.order_status === 'processing'">
                                    <span class="badge badge-blue">PROCESSING</span>
                                </template>
                                <template x-if="order.order_status === 'shipped'">
                                    <span class="badge" style="background:#ede9fe;color:#5b21b6;">SHIPPED</span>
                                </template>
                                <template x-if="order.order_status === 'delivered'">
                                    <span class="badge badge-green">DELIVERED</span>
                                </template>
                                <template x-if="order.order_status === 'pending'">
                                    <span class="badge badge-yellow">PENDING</span>
                                </template>
                                <template x-if="order.order_status === 'cancelled'">
                                    <span class="badge badge-red">CANCELLED</span>
                                </template>
                            </td>
                            <td class="col-payment">
                                <template x-if="order.payment_status === 'paid'">
                                    <span class="badge badge-green">PAID</span>
                                </template>
                                <template x-if="order.payment_status === 'partial'">
                                    <div>
                                        <span class="badge badge-yellow">PARTIAL</span>
                                        <div style="font-size: 0.7rem; color: #6b7280; margin-top: 0.25rem;">
                                            {{ $currency }} <span x-text="parseFloat(order.outstanding_amount).toFixed(2)"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="order.payment_status === 'pending'">
                                    <span class="badge badge-red">PENDING</span>
                                </template>
                            </td>
                        </tr>
                        
                        <!-- Expanded Details Row -->
                        <tr x-show="expandedRows[index]" x-transition style="background: #f9fafb;">
                            <td style="padding: 0; border-bottom: 2px solid #e5e7eb;"></td>
                                <td colspan="7" style="padding: 1rem; border-bottom: 2px solid #e5e7eb;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                                    <!-- Order Items -->
                                                    <div style="background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                                                        <h4 style="font-weight: 600; margin-bottom: 0.75rem; color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 0.5rem;">Order Items</h4>
                                                        <table style="width: 100%; font-size: 0.75rem;">
                                                            <thead>
                                                                <tr style="background: #f9fafb;">
                                                                    <th style="padding: 0.5rem; text-align: left;">Product</th>
                                                                    <th style="padding: 0.5rem; text-align: left;">Brand</th>
                                                                    <th style="padding: 0.5rem; text-align: center;">Qty</th>
                                                                    <th style="padding: 0.5rem; text-align: right;">Price</th>
                                                                    <th style="padding: 0.5rem; text-align: right;">Total</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <template x-for="(item, i) in order.items" :key="item.id || i">
                                                                    <tr>
                                                                        <td style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb;" x-text="item.product_name"></td>
                                                                        <td style="padding: 0.5rem; border-bottom: 1px solid #e5e7eb;" x-text="item.brand_name || item.brand"></td>
                                                                        <td style="padding: 0.5rem; text-align: center; border-bottom: 1px solid #e5e7eb;" x-text="item.quantity"></td>
                                                                        <td style="padding: 0.5rem; text-align: right; border-bottom: 1px solid #e5e7eb;">{{ $currency }} <span x-text="(item.unit_price || 0).toFixed(2)"></span></td>
                                                                        <td style="padding: 0.5rem; text-align: right; border-bottom: 1px solid #e5e7eb;">{{ $currency }} <span x-text="(item.line_total || 0).toFixed(2)"></span></td>
                                                                    </tr>
                                                                </template>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    
                                                    <!-- Customer & Vehicle Info -->
                                                    <div>
                                                        <div style="background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; margin-bottom: 1rem;">
                                                            <h4 style="font-weight: 600; margin-bottom: 0.75rem; color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 0.5rem;">Customer Info</h4>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Name:</span>
                                                                <span x-text="order.customer_name"></span>
                                                            </div>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Phone:</span>
                                                                <span x-text="order.customer_phone || 'N/A'"></span>
                                                            </div>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Email:</span>
                                                                <span x-text="order.customer_email || 'N/A'"></span>
                                                            </div>
                                                        </div>
                                                        
                                                        <div style="background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;">
                                                            <h4 style="font-weight: 600; margin-bottom: 0.75rem; color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 0.5rem;">Order Summary</h4>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Vehicle:</span>
                                                                <span x-text="order.vehicle"></span>
                                                            </div>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Sub Total:</span>
                                                                <span>{{ $currency }} <span x-text="parseFloat(order.sub_total || 0).toFixed(2)"></span></span>
                                                            </div>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">VAT:</span>
                                                                <span>{{ $currency }} <span x-text="parseFloat(order.vat || 0).toFixed(2)"></span></span>
                                                            </div>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Shipping:</span>
                                                                <span>{{ $currency }} <span x-text="parseFloat(order.shipping || 0).toFixed(2)"></span></span>
                                                            </div>
                                                            <div style="margin-bottom: 0.5rem; font-size: 0.875rem; padding-top: 0.5rem; border-top: 1px solid #e5e7eb;">
                                                                <span style="font-weight: 500; color: #6b7280; display: inline-block; width: 100px;">Total:</span>
                                                                <span style="font-weight: 600;">{{ $currency }} <span x-text="parseFloat(order.total).toFixed(2)"></span></span>
                                                            </div>
                                                            <template x-if="order.order_notes">
                                                                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb;">
                                                                    <span style="font-weight: 500; color: #6b7280; display: block; margin-bottom: 0.25rem;">Notes:</span>
                                                                    <span style="font-size: 0.875rem;" x-text="order.order_notes"></span>
                                                                </div>
                                                            </template>
                                                            <template x-if="order.internal_notes">
                                                                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb;">
                                                                    <span style="font-weight: 500; color: #6b7280; display: block; margin-bottom: 0.25rem;">Internal Notes:</span>
                                                                    <span style="font-size: 0.875rem;" x-text="order.internal_notes"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        
                                                        <!-- Action Buttons -->
                                                        <div style="background: white; padding: 1rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; margin-top: 1rem;">
                                                            <h4 style="font-weight: 600; margin-bottom: 0.75rem; color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 0.5rem;">Actions</h4>
                                                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                                                <button @click="downloadDelivery(order.id)" 
                                                                        style="width: 100%; padding: 0.625rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 500; text-align: left; display: flex; align-items: center; gap: 0.5rem;">
                                                                    <span>📄</span> Download Delivery Note
                                                                </button>
                                                                <button @click="downloadInvoice(order.id)" 
                                                                        style="width: 100%; padding: 0.625rem 1rem; background: #ec4899; color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 500; text-align: left; display: flex; align-items: center; gap: 0.5rem;">
                                                                    <span>📋</span> Download Invoice
                                                                </button>
                                                                <button @click="recordPayment(order)" 
                                                                        style="width: 100%; padding: 0.625rem 1rem; background: #f59e0b; color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 500; text-align: left; display: flex; align-items: center; gap: 0.5rem;">
                                                                    <span>💰</span> Record Balance Payment
                                                                </button>
                                                                <button @click="markAsDone(order.id)" 
                                                                        style="width: 100%; padding: 0.625rem 1rem; background: #10b981; color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 500; text-align: left; display: flex; align-items: center; gap: 0.5rem;">
                                                                    <span>✓</span> Mark Order as Done
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                    </div>
                                </td>
                            </tr>
                    </tbody>
                </template>
            </table>

            <!-- Payment Modal -->
    <div x-show="showPaymentModal" @click.away="showPaymentModal = false" x-cloak 
         style="display: none; position: fixed; inset: 0; z-index: 9999; overflow-y: auto; background: rgba(0,0,0,0.5);">
        <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem;">
            <div style="background: white; border-radius: 0.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 32rem; width: 100%; padding: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: #1f2937; border-bottom: 2px solid #ec4899; padding-bottom: 0.5rem;">Record Payment</h3>
                
                <!-- Order Info -->
                <div style="background: #f9fafb; padding: 1rem; border-radius: 0.375rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 500; color: #6b7280;">Order #:</span>
                        <span style="font-weight: 600;" x-text="currentOrder?.order_number"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 500; color: #6b7280;">Customer:</span>
                        <span x-text="currentOrder?.customer_name"></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="font-weight: 500; color: #6b7280;">Order Total:</span>
                        <span style="font-weight: 600;">{{ $currency }} <span x-text="parseFloat(currentOrder?.total || 0).toFixed(2)"></span></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-weight: 500; color: #dc2626;">Outstanding Amount:</span>
                        <span style="font-weight: 700; color: #dc2626;">{{ $currency }} <span x-text="parseFloat(currentOrder?.outstanding_amount || 0).toFixed(2)"></span></span>
                    </div>
                </div>

                <!-- Payment Amount -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Amount ({{ $currency }})</label>
                    <input type="number" step="0.01" x-model="paymentAmount" 
                           style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;"
                           placeholder="Enter amount" />
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                        <button @click="paymentAmount = parseFloat(currentOrder?.outstanding_amount || 0).toFixed(2)" 
                                style="padding: 0.375rem 0.75rem; background: #e5e7eb; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 0.75rem;">
                            Full Amount
                        </button>
                        <button @click="paymentAmount = (parseFloat(currentOrder?.outstanding_amount || 0) / 2).toFixed(2)" 
                                style="padding: 0.375rem 0.75rem; background: #e5e7eb; border: none; border-radius: 0.25rem; cursor: pointer; font-size: 0.75rem;">
                            50% Partial
                        </button>
                    </div>
                </div>

                <!-- Payment Method -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Payment Method *</label>
                    <select x-model="paymentMethod" 
                            style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;">
                        <option value="cash">Cash</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="online">Online Payment</option>
                    </select>
                </div>

                <!-- Payment Date -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Payment Date *</label>
                    <input type="date" x-model="paymentDate" 
                           style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;" />
                </div>

                <!-- Reference Number -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Reference Number</label>
                    <input type="text" x-model="referenceNumber" 
                           style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;"
                           placeholder="Transaction/Reference Number" />
                </div>

                <!-- Bank Name -->
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Bank Name</label>
                    <input type="text" x-model="bankName" 
                           style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;"
                           placeholder="Bank Name" />
                </div>

                <!-- Cheque Number (conditional) -->
                <div style="margin-bottom: 1rem;" x-show="paymentMethod === 'cheque'">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Cheque Number</label>
                    <input type="text" x-model="chequeNumber" 
                           style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 1rem;"
                           placeholder="Cheque Number" />
                </div>

                <!-- Payment Notes -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.5rem;">Notes</label>
                    <textarea x-model="paymentNotes" rows="2"
                              style="width: 100%; padding: 0.625rem 0.75rem; border: 2px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;"
                              placeholder="Add any notes about this payment..."></textarea>
                </div>

                <!-- Buttons -->
                <div style="display: flex; gap: 0.75rem;">
                    <button @click="showPaymentModal = false" 
                            style="flex: 1; padding: 0.75rem 1rem; background: #e5e7eb; color: #374151; border-radius: 0.375rem; border: none; cursor: pointer; font-weight: 500;">
                        Cancel
                    </button>
                    <button @click="submitPayment()" 
                            style="flex: 1; padding: 0.75rem 1rem; background: #ec4899; color: white; border-radius: 0.375rem; border: none; cursor: pointer; font-weight: 500;">
                        Record Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

        </div>

        <script>
            function orderSheet() {
                return {
                    orders: @json($orders),
                    activeTab: 'delivery',
                    
                    get pendingDeliveryOrders() {
                        // Orders not yet delivered (still in transit/processing)
                        return this.orders.filter(o => ['pending','processing','shipped'].includes(o.order_status));
                    },
                    get pendingPaymentOrders() {
                        // Any order with outstanding payment (partial or pending) — regardless of delivery status
                        return this.orders.filter(o => o.payment_status === 'partial' || o.payment_status === 'pending');
                    },
                    get filteredOrders() {
                        if (this.activeTab === 'delivery') return this.pendingDeliveryOrders;
                        if (this.activeTab === 'payment') return this.pendingPaymentOrders;
                        return this.orders;
                    },
                    expandedRows: {},
                    showPaymentModal: false,
                    currentOrder: null,
                    paymentAmount: 0,
                    paymentMethod: 'cash',
                    paymentDate: new Date().toISOString().split('T')[0],
                    referenceNumber: '',
                    bankName: '',
                    chequeNumber: '',
                    paymentNotes: '',

                    toggleExpand(index) {
                        // Create a new object to trigger Alpine reactivity
                        this.expandedRows = {
                            ...this.expandedRows,
                            [index]: !this.expandedRows[index]
                        };
                    },

                    recordPayment(order) {
                        if (parseFloat(order.outstanding_amount) <= 0) {
                            alert('Payment already completed for this order.');
                            return;
                        }
                        this.currentOrder = order;
                        this.paymentAmount = parseFloat(order.outstanding_amount).toFixed(2);
                        this.paymentMethod = 'cash';
                        this.paymentDate = new Date().toISOString().split('T')[0];
                        this.referenceNumber = '';
                        this.bankName = '';
                        this.chequeNumber = '';
                        this.paymentNotes = '';
                        this.showPaymentModal = true;
                    },

                    async submitPayment() {
                        if (!this.paymentAmount || this.paymentAmount <= 0) {
                            alert('Please enter a valid payment amount');
                            return;
                        }

                        if (parseFloat(this.paymentAmount) > parseFloat(this.currentOrder.outstanding_amount)) {
                            alert('Payment amount cannot exceed outstanding amount');
                            return;
                        }

                        if (!this.paymentMethod) {
                            alert('Please select a payment method');
                            return;
                        }

                        if (!this.paymentDate) {
                            alert('Please select a payment date');
                            return;
                        }

                        try {
                            const response = await fetch(`/admin/dashboard/record-payment/${this.currentOrder.id}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    amount: this.paymentAmount,
                                    payment_method: this.paymentMethod,
                                    payment_date: this.paymentDate,
                                    reference_number: this.referenceNumber,
                                    bank_name: this.bankName,
                                    cheque_number: this.chequeNumber,
                                    notes: this.paymentNotes
                                })
                            });

                            const data = await response.json();
                            if (data.success) {
                                this.showPaymentModal = false;
                                window.location.reload();
                            } else {
                                alert(data.message || 'Error recording payment');
                            }
                        } catch (error) {
                            console.error('Payment error:', error);
                            alert('Error recording payment');
                        }
                    },

                    downloadDelivery(orderId) {
                        window.open(`/admin/orders/${orderId}/delivery-note`, '_blank');
                    },

                    downloadInvoice(orderId) {
                        window.open(`/admin/orders/${orderId}/invoice`, '_blank');
                    },

                    async markAsDone(orderId) {
                        // Check if order is fully paid
                        const order = this.orders.find(o => o.id === orderId);
                        if (order && parseFloat(order.outstanding_amount) > 0) {
                            alert('Order cannot be marked as done until the balance payment is recorded.');
                            return;
                        }

                        if (!confirm('Mark this order as done?')) return;

                        try {
                            const response = await fetch(`/admin/dashboard/mark-done/${orderId}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });

                            const data = await response.json();
                            if (data.success) {
                                this.orders = this.orders.filter(o => o.id !== orderId);
                            } else {
                                alert(data.message || 'Error marking order as done');
                            }
                        } catch (error) {
                            alert('Error marking order as done');
                        }
                    }
                }
            }
        </script>
    </div>
</x-filament-panels::page>
