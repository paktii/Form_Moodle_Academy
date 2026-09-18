<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = $request->session()->get('portal.actor');

        if (! is_array($actor)) {
            return redirect()->route('login', $roles[0] ?? 'user');
        }

        abort_unless(in_array($actor['role'] ?? null, $roles, true), 403);

        return $next($request);
    }
}
