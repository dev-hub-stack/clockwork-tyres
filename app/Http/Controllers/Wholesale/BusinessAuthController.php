<?php

namespace App\Http\Controllers\Wholesale;

use App\Models\User;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BusinessAuthController extends BaseWholesaleController
{
    public function __construct(
        private readonly CurrentAccountResolver $currentAccountResolver,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $owner = User::query()
            ->where('email', strtolower(trim((string) $validated['email'])))
            ->first();

        if (! $owner instanceof User || ! Hash::check((string) $validated['password'], (string) $owner->password)) {
            return $this->error('Invalid email or password.', null, 401);
        }

        $token = $owner->createToken('clockwork-business-app')->plainTextToken;
        $context = $this->currentAccountResolver->resolve($request, $owner);

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'account_context' => $context->toArray(),
        ], 'Business login successful.');
    }
}
