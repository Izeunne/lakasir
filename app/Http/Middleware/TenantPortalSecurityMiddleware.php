<?php

namespace App\Http\Middleware;

use App\Filament\Tenant\Pages\Portal;
use App\Models\Tenants\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantPortalSecurityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Filament::auth()->check()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if (! str_starts_with($routeName, 'filament.tenant.')) {
            return $next($request);
        }

        $allowedWithoutGate = [
            'filament.tenant.pages.portal',
            'filament.tenant.auth.logout',
        ];

        if (in_array($routeName, $allowedWithoutGate, true)) {
            return $next($request);
        }

        $posRoutes = [
            'filament.tenant.pages.cashier',
            'filament.tenant.pages.p-o-s',
        ];

        if (in_array($routeName, $posRoutes, true)) {
            $posUserId = session('pos_user_id');

            if (! $posUserId || ! User::query()->whereKey($posUserId)->exists()) {
                session()->forget('pos_user_id');

                return redirect()->to(Portal::getUrl(['open_pos' => 1]));
            }

            return $next($request);
        }

        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (! $authUser || ! $authUser->isAdminPanelUser()) {
            session()->forget('admin_unlocked');

            return redirect()->to(Portal::getUrl());
        }

        if (session('admin_unlocked') !== true) {
            return redirect()->to(Portal::getUrl(['open_admin' => 1]));
        }

        return $next($request);
    }
}
