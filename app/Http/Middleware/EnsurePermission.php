<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement of a user's access restrictions (FR-02).
 *
 * Access restrictions are stored per user (`users.permissions`) after the
 * approved denormalization (ERD §2.1), so enforcement reads the signed-in
 * user's own restrictions.
 *
 * Usage: `->middleware('permission:manage_users')` or, for "any of"
 * semantics, `->middleware('permission:manage_inventory,manage_stock')`.
 *
 * The permission keys come from the centralized catalog
 * `App\Models\Role::PERMISSIONS` (RULE 6) — they are never invented inline.
 */
class EnsurePermission
{
    /**
     * @param  string  ...$permissions  One or more required permission keys.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        foreach ($permissions as $permission) {
            if ($user && $user->hasPermission($permission)) {
                return $next($request);
            }
        }

        // Hand the 403 page a safe, catalog-derived label only. Raw exception
        // messages, SQL errors and stack traces must never reach the user
        // (FR-02 / PRD §15).
        $labels = collect($permissions)
            ->map(fn (string $permission) => Role::PERMISSIONS[$permission] ?? null)
            ->filter()
            ->values();

        if ($request->hasSession()) {
            $request->session()->flash(
                'required_permission',
                $labels->isNotEmpty() ? $labels->implode(' or ') : 'the required access'
            );
        }

        abort(403, 'This area is not available for your role.');
    }
}
