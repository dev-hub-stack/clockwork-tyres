<?php

namespace App\Modules\Storefront\Support;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Support\Collection;

class StorefrontWorkspaceData
{
    /**
     * @return array<string, mixed>
     */
    public function forAccount(Account $account, User $owner): array
    {
        $subscription = $account->subscriptions()
            ->latest('starts_at')
            ->latest('id')
            ->first();

        $workspaceCustomer = $this->resolveWorkspaceCustomer($account);
        $addresses = $workspaceCustomer?->addresses()
            ->orderBy('address_type')
            ->orderBy('id')
            ->get() ?? collect();
        $primaryAddress = $addresses->first();
        $orders = $this->resolveOrders($workspaceCustomer);

        return [
            'profile' => $this->profilePayload(
                account: $account,
                owner: $owner,
                subscription: $subscription,
                workspaceCustomer: $workspaceCustomer,
                primaryAddress: $primaryAddress,
            ),
            'addresses' => $addresses
                ->map(fn (AddressBook $address) => $this->addressPayload($account, $address))
                ->values()
                ->all(),
            'orders' => $orders
                ->map(fn (Order $order) => $this->orderPayload($account, $order, $primaryAddress))
                ->values()
                ->all(),
        ];
    }

    private function resolveWorkspaceCustomer(Account $account): ?Customer
    {
        return $account->customers()
            ->with(['addresses', 'country'])
            ->where('external_source', 'business_owner_workspace')
            ->latest('id')
            ->first()
            ?? $account->customers()
                ->with(['addresses', 'country'])
                ->orderBy('id')
                ->first();
    }

    /**
     * @return Collection<int, Order>
     */
    private function resolveOrders(?Customer $workspaceCustomer): Collection
    {
        if (! $workspaceCustomer instanceof Customer) {
            return collect();
        }

        return $workspaceCustomer->orders()
            ->with('items')
            ->latest('issue_date')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    private function profilePayload(
        Account $account,
        User $owner,
        ?AccountSubscription $subscription,
        ?Customer $workspaceCustomer,
        ?AddressBook $primaryAddress,
    ): array {
        return [
            'businessName' => $account->name,
            'address' => $primaryAddress?->formatted_address ?? (string) ($workspaceCustomer?->address ?? ''),
            'email' => (string) ($workspaceCustomer?->email ?? $owner->email),
            'phone' => (string) ($primaryAddress?->phone_no ?? $workspaceCustomer?->primary_phone ?? ''),
            'country' => (string) ($primaryAddress?->country ?? $workspaceCustomer?->country?->name ?? ''),
            'licenseNumber' => (string) ($workspaceCustomer?->trade_license_number ?? $workspaceCustomer?->license_no ?? ''),
            'expiry' => $workspaceCustomer?->expiry?->toDateString() ?? '',
            'website' => (string) ($workspaceCustomer?->website ?? ''),
            'instagram' => (string) ($workspaceCustomer?->instagram ?? ''),
            'contactName' => $owner->name,
            'accountType' => $account->account_type?->value,
            'wholesaleEnabled' => (bool) $account->wholesale_enabled,
            'retailEnabled' => (bool) $account->retail_enabled,
            'subscription' => $subscription?->plan_code?->value ?? $account->base_subscription_plan?->value ?? 'basic',
        ];
    }

    private function addressPayload(Account $account, AddressBook $address): array
    {
        return [
            'id' => $address->id,
            'nickname' => $address->nickname ?: 'Business Address',
            'businessName' => $address->customer?->business_name ?: $account->name,
            'address' => (string) $address->address,
            'city' => (string) $address->city,
            'state' => (string) $address->state,
            'country' => (string) $address->country,
            'zip' => (string) ($address->zip ?? $address->zip_code ?? ''),
            'phone' => (string) ($address->phone_no ?? ''),
        ];
    }

    private function orderPayload(Account $account, Order $order, ?AddressBook $primaryAddress): array
    {
        $status = $order->order_status?->value;

        return [
            'id' => $order->display_number ?: sprintf('CW-%d', $order->id),
            'status' => in_array($status, ['processing', 'shipped', 'completed', 'cancelled'], true)
                ? $status
                : 'processing',
            'createdAt' => $order->issue_date?->toDateString() ?? $order->created_at?->toDateString() ?? now()->toDateString(),
            'supplierName' => $account->name,
            'trackingNumber' => (string) ($order->tracking_number ?? ''),
            'billing' => $this->orderAddressPayload($account, $primaryAddress),
            'shipping' => $this->orderAddressPayload($account, $primaryAddress),
            'lines' => $order->items
                ->map(fn (OrderItem $item) => $this->orderLinePayload($item))
                ->values()
                ->all(),
            'subtotal' => (float) ($order->sub_total ?? 0),
            'shippingAmount' => (float) ($order->shipping ?? 0),
            'vat' => (float) ($order->vat ?? 0),
            'total' => (float) ($order->total ?? 0),
        ];
    }

    private function orderAddressPayload(Account $account, ?AddressBook $address): array
    {
        return [
            'businessName' => $account->name,
            'address' => (string) ($address?->address ?? ''),
            'city' => (string) ($address?->city ?? ''),
            'country' => (string) ($address?->country ?? ''),
            'phone' => (string) ($address?->phone_no ?? ''),
        ];
    }

    private function orderLinePayload(OrderItem $item): array
    {
        return [
            'sku' => (string) ($item->display_sku ?? $item->sku ?? sprintf('ITEM-%d', $item->id)),
            'title' => $item->display_name,
            'size' => (string) (
                data_get($item->item_attributes, 'size')
                ?? data_get($item->variant_snapshot, 'full_size')
                ?? data_get($item->variant_snapshot, 'size')
                ?? ''
            ),
            'quantity' => (int) $item->quantity,
            'unitPrice' => (float) $item->unit_price,
            'origin' => 'own',
        ];
    }
}
