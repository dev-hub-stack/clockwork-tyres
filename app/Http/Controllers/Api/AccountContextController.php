<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Support\CurrentAccountContext;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountContextController extends Controller
{
    public function __construct(
        private readonly CurrentAccountResolver $resolver,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->contextPayload($request));
    }

    public function select(Request $request): JsonResponse
    {
        return response()->json($this->contextPayload($request));
    }

    private function contextPayload(Request $request): array
    {
        $context = $request->attributes->get('currentAccountContext');

        if (! $context instanceof CurrentAccountContext) {
            $context = $this->resolver->resolve($request, $request->user());
        }

        return $context->toArray();
    }
}
