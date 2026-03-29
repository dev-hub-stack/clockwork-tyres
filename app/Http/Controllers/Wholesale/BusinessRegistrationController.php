<?php

namespace App\Http\Controllers\Wholesale;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\CreateBusinessAccountRegistrationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessRegistrationController extends BaseWholesaleController
{
    public function __construct(
        private readonly CreateBusinessAccountRegistrationAction $createRegistration,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'country' => ['required', 'string', 'max:120'],
            'account_mode' => ['required', Rule::in(['retailer', 'supplier', 'both'])],
            'plan_preference' => ['required', Rule::in(['basic', 'premium'])],
            'accepts_terms' => ['required', 'accepted'],
            'accepts_privacy' => ['required', 'accepted'],
            'registration_source' => ['nullable', 'string', 'max:120'],
            'trade_license' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $registration = $this->createRegistration->execute([
            ...$validated,
            'supporting_document' => $request->file('trade_license'),
        ]);

        return $this->success(
            $registration,
            'Business account created successfully. You can continue with Clockwork setup.',
            201
        );
    }
}
