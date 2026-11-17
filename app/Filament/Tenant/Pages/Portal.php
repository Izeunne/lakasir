<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenants\About;
use App\Models\Tenants\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class Portal extends Page
{
    protected static ?string $slug = 'portal';

    public static function getRoutePath(): string
    {
        return '/';
    }

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament-panels::components.layout.base';

    protected static string $view = 'filament.tenant.pages.portal';

    public ?string $activeModal = null;

    public ?int $selectedUserId = null;

    public string $posPin = '';

    public string $adminPin = '';

    public ?string $errorMessage = null;

    public string $shopName = 'POS & Admin Portal';

    public ?string $shopLogo = null;

    public function mount(): void
    {
        session()->forget('admin_unlocked');

        $about = About::first();
        $this->shopName = $about?->shop_name ?? config('app.name', 'Lakasir');
        $this->shopLogo = $about?->logo_url ?? $about?->photo ?? null;

        if (request()->boolean('open_pos')) {
            $this->openPosModal();
        } elseif (request()->boolean('open_admin')) {
            $this->openAdminModal();
        }
    }

    public function getUsersProperty(): Collection
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    public function getHasAnyAdminPinProperty(): bool
    {
        return User::query()
            ->whereNotNull('pin')
            ->get()
            ->contains(fn (User $user): bool => $user->isAdminPanelUser() && $user->hasPin());
    }

    public function openPosModal(): void
    {
        $this->resetModalState();
        $this->activeModal = 'pos';
    }

    public function openAdminModal(): void
    {
        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (! $authUser || ! $authUser->isAdminPanelUser()) {
            Notification::make()
                ->title(__('Akses ditolak'))
                ->body(__('Akun yang login saat ini tidak memiliki hak akses Admin Panel.'))
                ->danger()
                ->send();

            return;
        }

        if (! $this->hasAnyAdminPin) {
            session(['admin_unlocked' => true]);

            Notification::make()
                ->title(__('PIN Admin belum diatur'))
                ->body(__('Silakan atur PIN untuk akun Admin di menu User agar Admin Panel terlindungi.'))
                ->warning()
                ->send();

            $this->redirect(Dashboard::getUrl());

            return;
        }

        $this->resetModalState();
        $this->activeModal = 'admin';
    }

    public function closeModal(): void
    {
        $this->resetModalState();
    }

    public function selectUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->posPin = '';
        $this->errorMessage = null;
    }

    public function enterPos(): void
    {
        $this->errorMessage = null;

        if (! $this->selectedUserId) {
            $this->errorMessage = __('Silakan pilih kasir terlebih dahulu.');

            return;
        }

        $user = User::find($this->selectedUserId);

        if (! $user) {
            $this->errorMessage = __('User tidak ditemukan.');

            return;
        }

        if (! $user->hasPin()) {
            $this->errorMessage = __('User ini belum memiliki PIN. Hubungi Admin untuk mengatur PIN di menu User.');

            return;
        }

        if (blank($this->posPin) || ! $user->verifyPin($this->posPin)) {
            $this->errorMessage = __('PIN kasir tidak sesuai.');
            $this->posPin = '';

            return;
        }

        session([
            'pos_user_id' => $user->id,
        ]);
        session()->forget('admin_unlocked');

        $this->redirect(Cashier::getUrl());
    }

    public function enterAdminPanel(): void
    {
        $this->errorMessage = null;

        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (! $authUser || ! $authUser->isAdminPanelUser()) {
            $this->errorMessage = __('Akses ditolak. Akun perangkat bukan Admin.');

            return;
        }

        if (blank($this->adminPin)) {
            $this->errorMessage = __('Masukkan PIN Admin terlebih dahulu.');

            return;
        }

        $matchedAdmin = User::query()
            ->whereNotNull('pin')
            ->get()
            ->first(fn (User $user): bool => $user->isAdminPanelUser() && $user->verifyPin($this->adminPin));

        if (! $matchedAdmin) {
            $this->errorMessage = __('PIN Admin salah.');
            $this->adminPin = '';

            return;
        }

        session(['admin_unlocked' => true]);

        $this->redirect(Dashboard::getUrl());
    }

    public function logoutDevice(): void
    {
        session()->forget(['pos_user_id', 'admin_unlocked']);
        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    private function resetModalState(): void
    {
        $this->activeModal = null;
        $this->selectedUserId = null;
        $this->posPin = '';
        $this->adminPin = '';
        $this->errorMessage = null;
    }
}
