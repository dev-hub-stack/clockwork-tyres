<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Support\CurrentAccountContext;
use App\Modules\Accounts\Support\CurrentAccountResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentAccount
{
    public function __construct(
        private readonly CurrentAccountResolver $resolver,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $context = $this->resolver->resolve($request, $user);

        $request->attributes->set('currentAccountContext', $context);
        $request->attributes->set('currentAccount', $context->currentAccount);

        return $next($request);
    }
}
