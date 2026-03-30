<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use App\Modules\Products\Actions\StageTyreImportAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TyreImportController extends Controller
{
    public function store(
        Request $request,
        CurrentAccountResolver $currentAccountResolver,
        StageTyreImportAction $stageTyreImportAction,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user?->can('view_products') ?? false, 403);

        $validated = $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240'],
        ]);

        $context = $currentAccountResolver->resolve($request, $user);
        $account = $context->currentAccount;

        if ($account === null) {
            return back()->withErrors([
                'import_file' => 'Select an active business account before staging a tyre import.',
            ]);
        }

        $uploadedFile = $validated['import_file'];
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());
        $storedPath = $uploadedFile->storeAs(
            'tyre-imports/tmp',
            Str::uuid().'.'.$extension,
            'local',
        );

        try {
            $batch = $stageTyreImportAction->execute(
                account: $account,
                filePath: Storage::disk('local')->path($storedPath),
                uploadedBy: $user,
                originalFileName: $uploadedFile->getClientOriginalName(),
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            return back()->withErrors([
                'import_file' => 'The tyre import could not be staged. '.$exception->getMessage(),
            ]);
        }

        Storage::disk('local')->delete($storedPath);

        return redirect()->back()->with('tyre_import_status', sprintf(
            'Staged %d tyre rows for %s from %s.',
            $batch->staged_rows,
            $account->name,
            $batch->source_file_name,
        ));
    }
}
