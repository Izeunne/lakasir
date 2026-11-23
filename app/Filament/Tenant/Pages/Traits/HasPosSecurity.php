<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Filament\Tenant\Pages\Dashboard;
use App\Models\Tenants\CartItem;
use App\Models\Tenants\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

trait HasPosSecurity
{
    public bool $isPosLocked = false;

    public ?int $selectedCashierId = null;

    public string $posPin = '';

    public ?string $posPinError = null;

    public bool $showAdminModal = false;

    public string $adminPin = '';

    public ?string $adminPinError = null;

    public string $adminPinAction = 'admin_panel'; // 'admin_panel', 'open_shift', 'close_shift'

    public function initializePosSecurity(): void
    {
        if (request()->boolean('lock_admin')) {
            session()->forget('admin_unlocked');
        }

        $posUserId = session('pos_user_id');
        $validUser = $posUserId ? User::query()->whereKey($posUserId)->first() : null;

        if (! $validUser) {
            $this->isPosLocked = true;
            /** @var ?User $authUser */
            $authUser = Filament::auth()->user();
            $this->selectedCashierId = $authUser?->id;
        } else {
            $this->isPosLocked = false;
            $this->selectedCashierId = $validUser->id;
        }

        if (request()->boolean('open_admin_modal')) {
            $this->openAdminPinModal();
        }
    }

    public function getCashiersProperty(): Collection
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    public function selectCashier(int $userId): void
    {
        $this->selectedCashierId = $userId;
        $this->posPin = '';
        $this->posPinError = null;
    }

    public function appendPosPin(string $digit): void
    {
        if (strlen($this->posPin) < 8) {
            $this->posPin .= $digit;
            $this->posPinError = null;
        }
    }

    public function backspacePosPin(): void
    {
        $this->posPin = substr($this->posPin, 0, -1);
        $this->posPinError = null;
    }

    public function clearPosPin(): void
    {
        $this->posPin = '';
        $this->posPinError = null;
    }

    public function unlockPos(): void
    {
        $this->posPinError = null;

        if (! $this->selectedCashierId) {
            $this->posPinError = __('Silakan pilih kasir terlebih dahulu.');

            return;
        }

        $user = User::find($this->selectedCashierId);

        if (! $user) {
            $this->posPinError = __('User kasir tidak ditemukan.');

            return;
        }

        if (! $user->hasPin()) {
            $this->posPinError = __('User ini belum memiliki PIN. Silakan atur PIN terlebih dahulu di menu User.');

            return;
        }

        if (blank($this->posPin) || ! $user->verifyPin($this->posPin)) {
            $this->posPinError = __('PIN kasir tidak sesuai.');
            $this->posPin = '';

            return;
        }

        session(['pos_user_id' => $user->id]);
        $this->isPosLocked = false;
        $this->posPin = '';
        $this->posPinError = null;

        if (method_exists($this, 'initializeShift')) {
            $this->initializeShift();
        }

        if (isset($this->cartItems)) {
            $this->cartItems = CartItem::query()
                ->select('*')
                ->with(['product.variations.options'])
                ->orderByDesc('created_at')
                ->cashier()
                ->get();
        }

        if (method_exists($this, 'calculateTotalPrice')) {
            $this->calculateTotalPrice();
        }

        Notification::make()
            ->title(__('Selamat bertugas, :name!', ['name' => $user->name]))
            ->success()
            ->send();
    }

    public function lockPos(): void
    {
        session()->forget('pos_user_id');
        $this->isPosLocked = true;
        $this->posPin = '';
        $this->posPinError = null;
    }

    public function switchCashier(): void
    {
        $this->lockPos();
    }

    public function requestAdminPin(string $action = 'admin_panel'): void
    {
        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if ($action === 'admin_panel') {
            $posUserId = session('pos_user_id');
            $posUser = $posUserId ? User::with('roles')->find($posUserId) : null;

            // Jika kasir yang sedang aktif di POS sudah merupakan Admin / Superadmin,
            // langsung arahkan ke Admin Panel tanpa perlu input PIN lagi.
            if (! $this->isPosLocked && $posUser && $posUser->isAdminPanelUser()) {
                session(['admin_unlocked' => true]);
                $this->redirect(Dashboard::getUrl());

                return;
            }

            if (! $authUser || ! $authUser->isAdminPanelUser()) {
                Notification::make()
                    ->title(__('Akses Ditolak'))
                    ->body(__('Akun yang login di perangkat ini tidak memiliki hak akses Admin Panel.'))
                    ->danger()
                    ->send();

                return;
            }

            if (! User::hasConfiguredAdminPin($authUser)) {
                session(['admin_unlocked' => true]);

                Notification::make()
                    ->title(__('PIN Admin Belum Diatur'))
                    ->body(__('Silakan atur PIN untuk akun Admin di menu User agar Admin Panel terlindungi.'))
                    ->warning()
                    ->send();

                $this->redirect(Dashboard::getUrl());

                return;
            }
        } else {
            // For open_shift and close_shift actions:
            if (! User::hasConfiguredAdminPin($authUser)) {
                if ($action === 'open_shift' && method_exists($this, 'executeOpenShift')) {
                    $this->executeOpenShift();
                } elseif ($action === 'close_shift' && method_exists($this, 'executeCloseShift')) {
                    $this->executeCloseShift();
                }

                return;
            }
        }

        $this->adminPinAction = $action;
        $this->showAdminModal = true;
        $this->adminPin = '';
        $this->adminPinError = null;
    }

    public function openAdminPinModal(): void
    {
        $this->requestAdminPin('admin_panel');
    }

    public function closeAdminPinModal(): void
    {
        $this->showAdminModal = false;
        $this->adminPin = '';
        $this->adminPinError = null;
        $this->adminPinAction = 'admin_panel';
    }

    public function appendAdminPin(string $digit): void
    {
        if (strlen($this->adminPin) < 8) {
            $this->adminPin .= $digit;
            $this->adminPinError = null;
        }
    }

    public function backspaceAdminPin(): void
    {
        $this->adminPin = substr($this->adminPin, 0, -1);
        $this->adminPinError = null;
    }

    public function clearAdminPin(): void
    {
        $this->adminPin = '';
        $this->adminPinError = null;
    }

    public function submitAdminPin(): void
    {
        $this->adminPinError = null;

        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (blank($this->adminPin)) {
            $this->adminPinError = __('Masukkan PIN Admin terlebih dahulu.');

            return;
        }

        $matchedAdmin = User::verifyAdminPin($this->adminPin, $authUser);

        if (! $matchedAdmin) {
            $this->adminPinError = __('PIN Admin tidak sesuai.');
            $this->adminPin = '';

            return;
        }

        $this->showAdminModal = false;
        $action = $this->adminPinAction;
        $this->adminPin = '';
        $this->adminPinAction = 'admin_panel';

        if ($action === 'open_shift') {
            if (method_exists($this, 'executeOpenShift')) {
                $this->executeOpenShift();
            }
        } elseif ($action === 'close_shift') {
            if (method_exists($this, 'executeCloseShift')) {
                $this->executeCloseShift();
            }
        } else {
            session(['admin_unlocked' => true]);
            $this->redirect(Dashboard::getUrl());
        }
    }

    public function logoutDevice(): void
    {
        session()->forget(['pos_user_id', 'admin_unlocked']);
        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }
}
