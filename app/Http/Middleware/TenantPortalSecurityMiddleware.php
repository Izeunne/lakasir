<?php

namespace App\Http\Middleware;

use App\Filament\Tenant\Pages\Cashier;
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
            'filament.tenant.auth.logout',
        ];

        if (in_array($routeName, $allowedWithoutGate, true)) {
            return $next($request);
        }

        // Direct user away from old portal page to Cashier POS
        if ($routeName === 'filament.tenant.pages.portal') {
            return redirect()->to(Cashier::getUrl());
        }

        $posRoutes = [
            'filament.tenant.pages.cashier',
            'filament.tenant.pages.p-o-s',
        ];

        // Cashier page manages its own lockscreen / PIN gate directly on the page
        if (in_array($routeName, $posRoutes, true)) {
            return $next($request);
        }

        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (! $authUser || ! $authUser->isAdminPanelUser()) {
            session()->forget('admin_unlocked');

            return redirect()->to(Cashier::getUrl());
        }

        if (session('admin_unlocked') !== true) {
            return redirect()->to(Cashier::getUrl(['open_admin_modal' => 1]));
        }

        return $next($request);
    }
}
