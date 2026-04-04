<?php

namespace App\Http\Controllers;

use App\Filament\Support\PanelAccess;
use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Support\TyreImageStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TyreImageController extends Controller
{
    protected function authorizeReadAccess(): void
    {
        abort_unless(PanelAccess::canAccessOperationalSurface('view_products'), 403);
    }

    protected function authorizeWriteAccess(): void
    {
        abort_unless(
            PanelAccess::canAccessOperationalSurfaceAny(['create_products', 'edit_products']),
            403
        );
    }

    public function index(Request $request)
    {
        $this->authorizeReadAccess();

        $account = $this->resolveCurrentAccount($request);

        if (! $account instanceof Account) {
            return redirect('/admin')->withErrors([
                'tyre_images' => 'Select an active business account before managing tyre images.',
            ]);
        }

        $query = $this->scopedQuery($account);

        if ($request->filled('sku')) {
            $query->where('source_sku', 'like', '%'.$request->string('sku')->trim().'%');
        }

        if ($request->filled('brand')) {
            $brand = $request->string('brand')->trim();
            $query->whereHas('tyreCatalogGroup', fn ($builder) => $builder->where('brand_name', 'like', '%'.$brand.'%'));
        }

        if ($request->filled('model')) {
            $model = $request->string('model')->trim();
            $query->whereHas('tyreCatalogGroup', fn ($builder) => $builder->where('model_name', 'like', '%'.$model.'%'));
        }

        if ($request->filled('size')) {
            $size = $request->string('size')->trim();
            $query->whereHas('tyreCatalogGroup', fn ($builder) => $builder->where('full_size', 'like', '%'.$size.'%'));
        }

        $images = $query
            ->orderBy('source_sku')
            ->paginate(15)
            ->appends($request->all());

        return view('tyres.images.index', [
            'account' => $account,
            'images' => $images,
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $this->authorizeReadAccess();

        $account = $this->resolveCurrentAccount($request);

        abort_unless($account instanceof Account, 404);

        $offer = $this->scopedQuery($account)->findOrFail($id);

        return view('tyres.images.edit', [
            'account' => $account,
            'offer' => $offer,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeWriteAccess();

        $account = $this->resolveCurrentAccount($request);

        abort_unless($account instanceof Account, 404);

        $offer = $this->scopedQuery($account)->findOrFail($id);
        $payload = [];

        foreach (['brand_image', 'product_image_1', 'product_image_2', 'product_image_3'] as $field) {
            if ($request->hasFile($field)) {
                TyreImageStorage::deleteManagedPath($offer->getRawOriginal($field));
                $payload[$field] = TyreImageStorage::storeUploadedFile($offer, $request->file($field), $field);
                continue;
            }

            if ($request->string("remove_{$field}")->toString() === '1') {
                TyreImageStorage::deleteManagedPath($offer->getRawOriginal($field));
                $payload[$field] = null;
            }
        }

        if ($payload !== []) {
            $offer->update($payload);
        }

        return redirect()
            ->route('admin.tyres.images.index')
            ->with('success', 'Tyre images updated successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReadAccess();

        $account = $this->resolveCurrentAccount($request);

        abort_unless($account instanceof Account, 404);

        $offers = $this->scopedQuery($account)
            ->orderBy('source_sku')
            ->get();

        $filename = 'tyre-images-'.$account->slug.'-'.now()->format('Y-m-d-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($offers): void {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'SKU',
                'Brand',
                'Model',
                'FullSize',
                'DotYear',
                'BrandImage',
                'ProductImage1',
                'ProductImage2',
                'ProductImage3',
            ]);

            foreach ($offers as $offer) {
                fputcsv($file, [
                    $offer->source_sku,
                    $offer->tyreCatalogGroup?->brand_name,
                    $offer->tyreCatalogGroup?->model_name,
                    $offer->tyreCatalogGroup?->full_size,
                    $offer->tyreCatalogGroup?->dot_year,
                    $offer->getRawOriginal('brand_image'),
                    $offer->getRawOriginal('product_image_1'),
                    $offer->getRawOriginal('product_image_2'),
                    $offer->getRawOriginal('product_image_3'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bulkImport(Request $request)
    {
        $this->authorizeWriteAccess();

        $account = $this->resolveCurrentAccount($request);

        abort_unless($account instanceof Account, 404);

        $request->validate([
            'importFile' => 'required|file|mimes:csv,txt',
        ]);

        $rows = array_map('str_getcsv', file($request->file('importFile')->getRealPath()));
        $header = array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            array_shift($rows) ?? []
        );

        $imported = 0;
        $errors = [];

        foreach ($rows as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            try {
                $data = array_combine($header, $row);

                if (! is_array($data)) {
                    $errors[] = 'A CSV row could not be mapped to the header columns.';
                    continue;
                }

                $sku = $this->csvValue($data, ['sku', 'source_sku']);

                if ($sku === null) {
                    $errors[] = 'Row skipped: SKU is required for tyre image import.';
                    continue;
                }

                $offer = $this->scopedQuery($account)
                    ->where('source_sku', $sku)
                    ->first();

                if (! $offer instanceof TyreAccountOffer) {
                    $errors[] = "Row skipped: No tyre offer found for SKU {$sku} in {$account->name}.";
                    continue;
                }

                $payload = [];

                foreach ([
                    'brand_image' => ['brandimage', 'brand_image'],
                    'product_image_1' => ['productimage1', 'product_image_1', 'image1'],
                    'product_image_2' => ['productimage2', 'product_image_2', 'image2'],
                    'product_image_3' => ['productimage3', 'product_image_3', 'image3'],
                ] as $field => $keys) {
                    $value = $this->csvValue($data, $keys);

                    if ($value === null) {
                        continue;
                    }

                    TyreImageStorage::deleteManagedPath($offer->getRawOriginal($field));
                    $payload[$field] = TyreImageStorage::normalizeImportPath($value);
                }

                if ($payload !== []) {
                    $offer->update($payload);
                    $imported++;
                }
            } catch (\Throwable $exception) {
                $errors[] = 'Row skipped: '.$exception->getMessage();
            }
        }

        return redirect()
            ->route('admin.tyres.images.index')
            ->with('success', "Import completed: {$imported} tyre image record(s) updated.")
            ->withErrors($errors);
    }

    private function resolveCurrentAccount(Request $request): ?Account
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return app(CurrentAccountResolver::class)->resolve($request, $user)->currentAccount;
    }

    private function scopedQuery(Account $account)
    {
        return TyreAccountOffer::query()
            ->with(['account', 'tyreCatalogGroup'])
            ->where('account_id', $account->id);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function csvValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
