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
            table { width: 100%; border-collapse: collapse; }
            th { background: #f9fafb; padding: 0.75rem 1.5rem; text-align: left; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #6b7280; }
            td { padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; font-size: 0.875rem; }
            tr:hover { background: #f9fafb; }
            .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
            .badge-blue { background: #dbeafe; color: #1e40af; }
            .badge-green { background: #d1fae5; color: #065f46; }
            .badge-yellow { background: #fef3c7; color: #92400e; }
            .badge-red { background: #fee2e2; color: #991b1b; }
            .link-pink { color: #ec4899; font-weight: 500; text-decoration: none; }
            .link-pink:hover { color: #db2777; text-decoration: underline; }
            .action-btn { background: none; border: none; cursor: pointer; padding: 0.25rem; margin: 0 0.25rem; }
            .action-btn svg { width: 1.25rem; height: 1.25rem; }
            .action-btn-blue { color: #3b82f6; }
            .action-btn-blue:hover { color: #2563eb; }
            .action-btn-pink { color: #ec4899; }
            .action-btn-pink:hover { color: #db2777; }
            .action-btn-yellow { color: #f59e0b; }
            .action-btn-yellow:hover { color: #d97706; }
            .action-btn-green { color: #10b981; }
            .action-btn-green:hover { color: #059669; }
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
                <h2>${{ number_format($monthlyRevenue, 2) }}</h2>
            </div>
            <div class="stat-card card-red">
                <p>Today's Orders</p>
                <h2>{{ $todayOrders }}</h2>
            </div>
            <div class="stat-card card-orange">
                <p>Notifications</p>
                <h2>{{ $notifications }}</h2>
            </div>
        </div>

        <!-- Order Sheet Table -->
        <div class="order-table" x-data="orderSheet()">
            <div class="table-header">
                <h3>Pending Orders - Order Sheet</h3>
                <button @click="refreshOrders()" class="btn-refresh">↻ Refresh</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Wheel Brand</th>
                        <th>Vehicle</th>
                        <th>Tracking</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="order in orders" :key="order.id">
                        <tr>
                            <td x-text="order.created_at"></td>
                            <td>
                                <a :href="'/admin/orders/' + order.id" class="link-pink" x-text="order.order_number"></a>
                            </td>
                            <td x-text="order.customer_name"></td>
                            <td x-text="order.wheel_brand"></td>
                            <td x-text="order.vehicle"></td>
                            <td>
                                <span class="badge badge-blue" x-text="order.tracking_number || 'PENDING'"></span>
                            </td>
                            <td>
                                <template x-if="order.payment_status === 'paid'">
                                    <span class="badge badge-green">PAID</span>
                                </template>
                                <template x-if="order.payment_status === 'partial'">
                                    <div>
                                        <span class="badge badge-yellow">PARTIAL</span>
                                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                            $<span x-text="parseFloat(order.outstanding_amount).toFixed(2)"></span> remaining
                                        </div>
                                    </div>
                                </template>
                                <template x-if="order.payment_status === 'pending'">
                                    <span class="badge badge-red">PENDING</span>
                                </template>
                            </td>
                            <td>
                                <button @click="downloadDelivery(order.id)" class="action-btn action-btn-blue" title="Download Delivery Note">
                                    📄
                                </button>
                                <button @click="downloadInvoice(order.id)" class="action-btn action-btn-pink" title="Download Invoice">
                                    📋
                                </button>
                                <template x-if="order.outstanding_amount > 0">
                                    <button @click="recordPayment(order)" class="action-btn action-btn-yellow" title="Record Payment">
                                        💰
                                    </button>
                                </template>
                                <template x-if="order.payment_status === 'paid'">
                                    <button @click="markAsDone(order.id)" class="action-btn action-btn-green" title="Mark as Done">
                                        ✓
                                    </button>
                                </template>
                            </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Payment Modal -->
    <div x-show="showPaymentModal" @click.away="showPaymentModal = false" x-cloak 
         style="display: none; position: fixed; inset: 0; z-index: 9999; overflow-y: auto; background: rgba(0,0,0,0.5);">
        <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem;">
            <div style="background: white; border-radius: 0.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 28rem; width: 100%; padding: 1.5rem;">
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Record Balance Payment</h3>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Payment Amount</label>
                    <input type="number" step="0.01" x-model="paymentAmount" 
                           style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;" />
                    <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem;">
                        Outstanding: $<span x-text="currentOrder?.outstanding_amount"></span>
                    </p>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.25rem;">Payment Method</label>
                    <select x-model="paymentMethod" 
                            style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;">
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                    </select>
                </div>
                <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                    <button @click="showPaymentModal = false" 
                            style="flex: 1; padding: 0.5rem 1rem; background: #e5e7eb; color: #374151; border-radius: 0.375rem; border: none; cursor: pointer;">
                        Cancel
                    </button>
                    <button @click="submitPayment()" 
                            style="flex: 1; padding: 0.5rem 1rem; background: #ec4899; color: white; border-radius: 0.375rem; border: none; cursor: pointer;">
                        Record Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

        <!-- Payment Modal -->
        <div x-show="showPaymentModal" @click.away="showPaymentModal = false" x-cloak style="display: none;" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold mb-4">Record Balance Payment</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Amount</label>
                            <input type="number" step="0.01" x-model="paymentAmount" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500" />
                            <p class="text-sm text-gray-500 mt-1">Outstanding: $<span x-text="currentOrder?.outstanding_amount"></span></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select x-model="paymentMethod" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500">
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button @click="showPaymentModal = false" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                            Cancel
                        </button>
                        <button @click="submitPayment()" class="flex-1 px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition">
                            Record Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function orderSheet() {
                return {
                    orders: @json($orders),
                    showPaymentModal: false,
                    currentOrder: null,
                    paymentAmount: 0,
                    paymentMethod: 'cash',

                    refreshOrders() {
                        window.location.reload();
                    },

                    recordPayment(order) {
                        this.currentOrder = order;
                        this.paymentAmount = order.outstanding_amount;
                        this.showPaymentModal = true;
                    },

                    async submitPayment() {
                        try {
                            const response = await fetch(`/admin/dashboard/record-payment/${this.currentOrder.id}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    payment_amount: this.paymentAmount,
                                    payment_method: this.paymentMethod
                                })
                            });

                            const data = await response.json();
                            if (data.success) {
                                window.location.reload();
                            } else {
                                alert(data.message || 'Error recording payment');
                            }
                        } catch (error) {
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
