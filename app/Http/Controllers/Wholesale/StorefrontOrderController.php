<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountContext;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorefrontOrderController extends BaseWholesaleController
{
    public function __construct(
        private readonly CurrentAccountResolver $currentAccountResolver,
        private readonly OrderService $orderService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $owner = $request->user();

        if (! $owner) {
            return $this->error('Unauthenticated.', null, 401);
        }

        $context = $request->attributes->get('currentAccountContext');

        if (! $context instanceof CurrentAccountContext) {
            $context = $this->currentAccountResolver->resolve($request, $owner);
        }

        $account = $context->currentAccount;

        if (! $account instanceof Account) {
            return $this->error('No active business account is selected.', null, 422);
        }

        if (! $account->supportsRetailStorefront()) {
            return $this->error('This account does not have retail storefront checkout enabled.', null, 403);
        }

        $validated = $request->validate([
            'billing' => ['required', 'array'],
            'billing.businessName' => ['required', 'string', 'max:200'],
            'billing.country' => ['required', 'string', 'max:120'],
            'billing.state' => ['required', 'string', 'max:120'],
            'billing.city' => ['required', 'string', 'max:120'],
            'billing.zip' => ['required', 'string', 'max:40'],
            'billing.address' => ['required', 'string', 'max:255'],
            'billing.phone' => ['required', 'string', 'max:50'],
            'shipping' => ['required', 'array'],
            'shipping.businessName' => ['required', 'string', 'max:200'],
            'shipping.country' => ['required', 'string', 'max:120'],
            'shipping.state' => ['required', 'string', 'max:120'],
            'shipping.city' => ['required', 'string', 'max:120'],
            'shipping.zip' => ['required', 'string', 'max:40'],
            'shipping.address' => ['required', 'string', 'max:255'],
            'shipping.phone' => ['required', 'string', 'max:50'],
            'purchaseOrderNo' => ['nullable', 'string', 'max:120'],
            'orderNotes' => ['nullable', 'string', 'max:2000'],
            'deliveryOption' => ['required', Rule::in(['Pick up from warehouse', 'Delivery'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:120'],
            'items.*.slug' => ['required', 'string', 'max:200'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.size' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'numeric', 'min:0'],
            'items.*.origin' => ['required', Rule::in(['own', 'supplier'])],
            'items.*.availabilityLabel' => ['nullable', 'string', 'max:120'],
        ]);

        $workspaceCustomer = $this->resolveWorkspaceCustomer($account, $owner);

        $order = DB::transaction(function () use ($validated, $account, $workspaceCustomer): Order {
            $billingAddress = $this->upsertAddress(
                workspaceCustomer: $workspaceCustomer,
                addressType: 1,
                nickname: 'Billing Address',
                payload: $validated['billing'],
            );

            $shippingAddress = $this->upsertAddress(
                workspaceCustomer: $workspaceCustomer,
                addressType: 2,
                nickname: 'Shipping Address',
                payload: $validated['shipping'],
            );

            $workspaceCustomer->forceFill([
                'business_name' => $validated['billing']['businessName'],
                'phone' => $validated['billing']['phone'],
                'address' => $validated['billing']['address'],
                'city' => $validated['billing']['city'],
                'state' => $validated['billing']['state'],
                'website' => $workspaceCustomer->website,
            ])->save();

            $order = $this->orderService->createOrder([
                'document_type' => DocumentType::ORDER->value,
                'customer_id' => $workspaceCustomer->id,
                'currency' => 'AED',
                'channel' => 'retail-storefront',
                'external_source' => 'clockwork_tyres_storefront',
                'tax_inclusive' => true,
                'shipping' => 25,
                'order_notes' => $this->mergeOrderNotes(
                    purchaseOrderNumber: $validated['purchaseOrderNo'] ?? null,
                    orderNotes: $validated['orderNotes'] ?? null,
                ),
                'delivery_options' => $validated['deliveryOption'],
                'shipping_address_id' => $shippingAddress->id,
                'items' => array_map(
                    fn (array $item): array => $this->mapCartItemToOrderItem($item),
                    $validated['items']
                ),
            ]);

            $order->forceFill([
                'order_number' => sprintf('CW-RT-%06d', $order->id),
                'order_status' => OrderStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'payment_method' => 'in_store',
                'delivery_options' => $validated['deliveryOption'],
                'shipping_address_id' => $shippingAddress->id,
                'sub_total' => $this->subtotalFor($validated['items']),
                'shipping' => 25,
                'vat' => $this->vatFor($validated['items']),
                'tax' => 0,
                'total' => $this->totalFor($validated['items']),
            ])->save();

            return $order->fresh(['items']);
        });

        return $this->success([
            'order' => [
                'id' => $order->id,
                'orderNumber' => $order->order_number,
                'status' => $order->order_status?->value ?? 'pending',
                'total' => (float) $order->total,
            ],
        ], 'Storefront order created successfully.', 201);
    }

    private function resolveWorkspaceCustomer(Account $account, $owner): Customer
    {
        return $account->customers()
            ->where('external_source', 'business_owner_workspace')
            ->first()
            ?? $account->customers()->first()
            ?? Customer::query()->create([
                'customer_type' => $account->isSupplier() && ! $account->isRetailer() ? 'wholesale' : 'dealer',
                'business_name' => $account->name,
                'email' => $owner->email,
                'account_id' => $account->id,
                'external_source' => 'business_owner_workspace',
                'external_customer_id' => sprintf('account-%d', $account->id),
                'status' => 'active',
            ]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function upsertAddress(Customer $workspaceCustomer, int $addressType, string $nickname, array $payload): AddressBook
    {
        return AddressBook::query()->updateOrCreate(
            [
                'customer_id' => $workspaceCustomer->id,
                'address_type' => $addressType,
            ],
            [
                'nickname' => $nickname,
                'address' => $payload['address'],
                'city' => $payload['city'],
                'state' => $payload['state'],
                'country' => $payload['country'],
                'zip' => $payload['zip'],
                'zip_code' => $payload['zip'],
                'phone_no' => $payload['phone'],
                'email' => $workspaceCustomer->email,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapCartItemToOrderItem(array $item): array
    {
        [$brandName, $modelName] = $this->splitTitle((string) $item['title']);

        return [
            'sku' => $item['sku'],
            'product_name' => $item['title'],
            'brand_name' => $brandName,
            'model_name' => $modelName,
            'quantity' => (int) $item['quantity'],
            'unit_price' => (float) $item['unitPrice'],
            'tax_inclusive' => true,
            'item_attributes' => [
                'size' => $item['size'],
                'slug' => $item['slug'],
                'origin' => $item['origin'],
                'availability_label' => $item['availabilityLabel'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function subtotalFor(array $items): float
    {
        return round(collect($items)->sum(
            fn (array $item): float => ((float) $item['unitPrice']) * ((int) $item['quantity'])
        ), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function vatFor(array $items): float
    {
        return round($this->subtotalFor($items) * 0.05, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function totalFor(array $items): float
    {
        return round($this->subtotalFor($items) + 25 + $this->vatFor($items), 2);
    }

    private function mergeOrderNotes(?string $purchaseOrderNumber, ?string $orderNotes): ?string
    {
        $notes = [];

        if ($purchaseOrderNumber) {
            $notes[] = 'Purchase Order: '.trim($purchaseOrderNumber);
        }

        if ($orderNotes) {
            $notes[] = trim($orderNotes);
        }

        return $notes !== [] ? implode("\n\n", $notes) : null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitTitle(string $title): array
    {
        $parts = preg_split('/\s+/', trim($title), 2) ?: [];

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }
}
