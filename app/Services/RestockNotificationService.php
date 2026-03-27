<?php

namespace App\Services;

use App\Mail\RestockAvailableMail;
use App\Mail\RestockConfirmationMail;
use App\Models\Addon as LegacyAddon;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Products\Models\AddOn as ModuleAddon;
use App\Modules\Products\Models\ProductVariant;
use App\Support\TransactionalCustomerMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class RestockNotificationService
{
    public function __construct(
        private readonly TransactionalCustomerMail $mail,
    ) {}

    public function subscribeVariant(ProductVariant $variant, string $email): bool
    {
        return $this->subscribe($variant, $email);
    }

    public function subscribeAddon(LegacyAddon|ModuleAddon $addon, string $email): bool
    {
        return $this->subscribe($addon, $email);
    }

    public function handleInventoryAvailabilityChange(ProductInventory $inventory): void
    {
        if (! $inventory->product_variant_id && ! $inventory->add_on_id) {
            return;
        }

        $oldQuantity = $inventory->wasRecentlyCreated ? 0 : (int) ($inventory->getOriginal('quantity') ?? 0);
        $oldEtaQty = $inventory->wasRecentlyCreated ? 0 : (int) ($inventory->getOriginal('eta_qty') ?? 0);
        $oldEta = $inventory->wasRecentlyCreated ? null : $inventory->getOriginal('eta');

        $previous = $this->summarizeAvailability($inventory, $oldQuantity, $oldEtaQty, $oldEta);
        $current = $this->summarizeAvailability($inventory, (int) ($inventory->quantity ?? 0), (int) ($inventory->eta_qty ?? 0), $inventory->eta);

        if ($previous['available'] || ! $current['available']) {
            return;
        }

        if ($inventory->product_variant_id) {
            $variant = ProductVariant::query()
                ->with(['product.finish', 'finishRelation'])
                ->find($inventory->product_variant_id);

            if (! $variant) {
                return;
            }

            $this->sendAvailabilityEmails(
                $variant,
                $this->normalizedRecipients($variant->notify_restock),
                $this->buildVariantItemData($variant, $current),
                $current['eta_only'],
            );

            return;
        }

        $addon = LegacyAddon::query()->with('category')->find($inventory->add_on_id);

        if (! $addon) {
            return;
        }

        $this->sendAvailabilityEmails(
            $addon,
            $this->normalizedRecipients($addon->notify_restock),
            $this->buildAddonItemData($addon, $current),
            $current['eta_only'],
        );
    }

    private function subscribe(Model $item, string $email): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);

        if (! $normalizedEmail) {
            return false;
        }

        $recipients = $this->normalizedRecipients($item->notify_restock ?? []);

        if (in_array($normalizedEmail, $recipients, true)) {
            return false;
        }

        $recipients[] = $normalizedEmail;
        $this->persistRecipients($item, $recipients);

        try {
            $itemData = $item instanceof ProductVariant
                ? $this->buildVariantItemData($item)
                : $this->buildAddonItemData($this->resolveAddonModel($item));

            $this->mail->send($normalizedEmail, new RestockConfirmationMail($itemData), [
                'type' => 'restock_confirmation',
                'item_id' => $item->getKey(),
                'item_class' => $item::class,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to send restock confirmation email', [
                'item_id' => $item->getKey(),
                'item_class' => $item::class,
                'email' => $normalizedEmail,
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }

    private function sendAvailabilityEmails(Model $item, array $recipients, array $itemData, bool $isEta): void
    {
        if ($recipients === []) {
            return;
        }

        $remaining = [];

        foreach ($recipients as $recipient) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Dropping invalid restock recipient', [
                    'item_id' => $item->getKey(),
                    'item_class' => $item::class,
                    'email' => $recipient,
                ]);

                continue;
            }

            try {
                $status = $this->mail->send($recipient, new RestockAvailableMail($itemData, $isEta), [
                    'type' => 'restock_available',
                    'item_id' => $item->getKey(),
                    'item_class' => $item::class,
                    'is_eta' => $isEta,
                ]);

                if ($status !== 'sent') {
                    $remaining[] = $recipient;
                }
            } catch (Throwable $exception) {
                Log::warning('Failed to send restock availability email', [
                    'item_id' => $item->getKey(),
                    'item_class' => $item::class,
                    'email' => $recipient,
                    'error' => $exception->getMessage(),
                ]);

                $remaining[] = $recipient;
            }
        }

        if ($remaining !== $recipients) {
            $this->persistRecipients($item, $remaining);
        }
    }

    private function summarizeAvailability(ProductInventory $inventory, int $quantity, int $etaQty, ?string $eta): array
    {
        $scope = ProductInventory::query();

        if ($inventory->product_variant_id) {
            $scope->where('product_variant_id', $inventory->product_variant_id);
        } else {
            $scope->where('add_on_id', $inventory->add_on_id);
        }

        if ($inventory->exists) {
            $scope->whereKeyNot($inventory->id);
        }

        $otherQty = (int) (clone $scope)->sum('quantity');
        $otherEtaQty = (int) (clone $scope)->sum('eta_qty');
        $otherHasEtaText = (clone $scope)
            ->whereNotNull('eta')
            ->where('eta', '!=', '')
            ->exists();

        $totalQuantity = max(0, $quantity) + $otherQty;
        $totalEtaQty = max(0, $etaQty) + $otherEtaQty;
        $hasEtaText = $otherHasEtaText || $this->hasEtaText($eta);

        return [
            'quantity' => $totalQuantity,
            'eta_qty' => $totalEtaQty,
            'eta' => $this->normalizeEta($eta),
            'available' => $totalQuantity > 0 || $totalEtaQty > 0 || $hasEtaText,
            'eta_only' => $totalQuantity <= 0 && ($totalEtaQty > 0 || $hasEtaText),
        ];
    }

    private function buildVariantItemData(ProductVariant $variant, array $availability = []): array
    {
        $variant->loadMissing(['product.finish', 'finishRelation']);

        $product = $variant->product;
        $finishName = $variant->finishRelation?->finish ?? $product?->finish?->finish ?? $variant->finish;

        return [
            'type' => 'wheel',
            'type_label' => 'Wheel',
            'name' => $product?->product_full_name ?: $product?->name ?: $variant->sku,
            'finish_name' => $finishName,
            'sku' => $variant->sku,
            'detail_lines' => array_values(array_filter([
                $product?->product_full_name ?: $product?->name,
                $finishName,
                $variant->size,
                $variant->bolt_pattern,
            ])),
            'image_url' => $this->resolveImageUrl(Arr::first($product?->images?->all() ?? [])),
            'product_url' => $this->resolveFrontendUrl($product->slug ?? null, $variant->sku, '/alloy-wheels'),
            'size' => $variant->size,
            'bolt_pattern' => $variant->bolt_pattern,
            'weight' => $variant->weight,
            'offset' => $variant->offset,
            'hub_bore' => $variant->hub_bore,
            'max_wheel_load' => $variant->max_wheel_load,
            'backspacing' => $variant->backspacing,
            'quantity' => $availability['quantity'] ?? null,
            'eta_qty' => $availability['eta_qty'] ?? null,
            'eta' => $availability['eta'] ?? null,
        ];
    }

    private function buildAddonItemData(LegacyAddon $addon, array $availability = []): array
    {
        return [
            'type' => 'addon',
            'type_label' => 'Accessory',
            'name' => $addon->title,
            'sku' => $addon->part_number,
            'detail_lines' => array_values(array_filter([
                $addon->part_number,
                $addon->category?->name,
            ])),
            'image_url' => $addon->image_1_url,
            'product_url' => $this->resolveFrontendUrl(null, null, '/accessories'),
            'quantity' => $availability['quantity'] ?? null,
            'eta_qty' => $availability['eta_qty'] ?? null,
            'eta' => $availability['eta'] ?? null,
        ];
    }

    private function persistRecipients(Model $item, array $recipients): void
    {
        $item->forceFill([
            'notify_restock' => array_values($recipients),
        ])->saveQuietly();
    }

    private function normalizedRecipients(mixed $recipients): array
    {
        return Collection::make($recipients)
            ->map(fn ($recipient) => $this->normalizeEmail($recipient))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeEmail(mixed $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
    }

    private function hasEtaText(?string $eta): bool
    {
        return $this->normalizeEta($eta) !== null;
    }

    private function normalizeEta(?string $eta): ?string
    {
        $normalized = trim((string) ($eta ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveAddonModel(Model $item): LegacyAddon
    {
        if ($item instanceof LegacyAddon) {
            return $item;
        }

        return LegacyAddon::findOrFail($item->getKey());
    }

    private function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $cdnUrl = rtrim((string) config('filesystems.disks.s3.url', ''), '/');

        return $cdnUrl !== '' ? $cdnUrl . '/' . ltrim($path, '/') : null;
    }

    private function resolveFrontendUrl(?string $slug, ?string $sku, string $fallbackPath): string
    {
        $baseUrl = rtrim((string) env('FRONTEND_URL', 'https://tunerstopwholesale.com'), '/');

        if ($slug && $sku) {
            return $baseUrl . '/' . trim($slug, '/') . '/' . trim($sku, '/');
        }

        return $baseUrl . $fallbackPath;
    }
}