<div class="p-6 bg-white rounded-lg shadow">
    <h2 class="text-2xl font-bold mb-6">System Settings</h2>

    @if (session()->has('message'))
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save">
        <!-- Company Information -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Company Information</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Company Name</label>
                    <input type="text" wire:model="company_name" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Email</label>
                    <input type="email" wire:model="company_email" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Phone</label>
                    <input type="text" wire:model="company_phone" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Tax Registration</label>
                    <input type="text" wire:model="tax_registration_number" class="w-full border rounded px-3 py-2">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium mb-2">Address</label>
                    <textarea wire:model="company_address" rows="3" class="w-full border rounded px-3 py-2"></textarea>
                </div>
            </div>
        </div>

        <!-- Currency Settings -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Currency Settings</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Currency Code</label>
                    <input type="text" wire:model="currency_code" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Symbol</label>
                    <input type="text" wire:model="currency_symbol" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Decimal Places</label>
                    <input type="number" wire:model="decimal_places" class="w-full border rounded px-3 py-2">
                </div>
            </div>
        </div>

        <!-- Tax Settings -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Tax Settings</h3>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Tax Name</label>
                    <input type="text" wire:model="tax_name" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Tax Rate (%)</label>
                    <input type="number" step="0.01" wire:model="tax_rate" class="w-full border rounded px-3 py-2">
                </div>
                <div class="flex items-center">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="tax_inclusive_default" class="mr-2">
                        <span class="text-sm font-medium">Tax Inclusive</span>
                    </label>
                </div>
            </div>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            Save Settings
        </button>
    </form>
</div>
