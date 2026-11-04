<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalRole
{
    private const TAB_ROLE_PARAMETER = '_portal_role';

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = $request->session()->get('portal.actor');

        if (! is_array($actor)) {
            return redirect()->guest(route('login'));
        }

        if (config('course-workflow.dev_role_switcher')) {
            $request->attributes->set('portal.tab_role_enabled', true);
        }

        $tabRole = $this->tabRole($request);
        if ($tabRole !== null) {
            $actor['role'] = $tabRole;
            $actor['label'] = $tabRole === 'officer' ? 'เจ้าหน้าที่' : 'ผู้ใช้งาน';
        }
        $request->attributes->set('portal.actor', $actor);

        abort_unless(in_array($actor['role'] ?? null, $roles, true), 403);

        $response = $next($request);

        if ($tabRole !== null && $response instanceof RedirectResponse) {
            $location = $response->headers->get('Location');
            if (is_string($location) && $this->isSameOrigin($request, $location)) {
                $response->headers->set('Location', $this->withTabRole($location, $tabRole));
            }
        }

        return $response;
    }

    private function tabRole(Request $request): ?string
    {
        if (! config('course-workflow.dev_role_switcher')) {
            return null;
        }

        $role = $request->input(self::TAB_ROLE_PARAMETER);
        if ($role === null) {
            return null;
        }

        abort_unless(is_string($role), 403);
        abort_unless(in_array($role, $request->session()->get('portal.available_roles', []), true), 403);

        return $role;
    }

    private function isSameOrigin(Request $request, string $location): bool
    {
        $host = parse_url($location, PHP_URL_HOST);

        return $host === null || strcasecmp($host, $request->getHost()) === 0;
    }

    private function withTabRole(string $location, string $role): string
    {
        if (preg_match('/(?:^|[?&])'.preg_quote(self::TAB_ROLE_PARAMETER, '/').'=/i', $location)) {
            return $location;
        }

        [$url, $fragment] = array_pad(explode('#', $location, 2), 2, null);
        $url .= str_contains($url, '?') ? '&' : '?';
        $url .= self::TAB_ROLE_PARAMETER.'='.rawurlencode($role);

        return $fragment === null ? $url : $url.'#'.$fragment;
    }
}
