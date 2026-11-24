<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Models\Tenants\CashDrawer;
use App\Models\Tenants\User;
use App\Services\Tenants\ShiftReportService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

trait HasShiftManagement
{
    public ?int $activeDrawerId = null;
    public ?array $activeShiftSummary = null;
    public bool $showOpenStoreModal = false;
    public bool $hasStaleOpenDrawer = false;
    public ?string $staleDrawerOpenedAt = null;

    public function initializeShift(): void
    {
        $service = app(ShiftReportService::class);
        $drawer = $service->getActiveDrawer();
        $this->activeDrawerId = $drawer?->id;

        if (! $drawer) {
            $this->showOpenStoreModal = true;
            $this->hasStaleOpenDrawer = false;
            $this->staleDrawerOpenedAt = null;
        } elseif ($drawer->opened_at && ! $drawer->opened_at->isToday()) {
            $this->showOpenStoreModal = true;
            $this->hasStaleOpenDrawer = true;
            $this->staleDrawerOpenedAt = $drawer->opened_at->format('d/m/Y H:i');
        } else {
            $this->showOpenStoreModal = false;
            $this->hasStaleOpenDrawer = false;
            $this->staleDrawerOpenedAt = null;
        }
    }

    public function openStoreFromModal(): void
    {
        $service = app(ShiftReportService::class);
        $userId = (int) (pos_user_id() ?? auth()->id() ?? 1);
        $activeDrawer = $service->getActiveDrawer();

        // Jika masih ada shift terbuka dari hari sebelumnya (lupa tutup toko),
        // otomatis tutup dan cetak laporan tutup toko dulu sebelum buka toko baru.
        if ($activeDrawer && $activeDrawer->opened_at && ! $activeDrawer->opened_at->isToday()) {
            $summary = $service->getShiftSummary($activeDrawer);
            $service->closeShift($activeDrawer, $userId);
            $this->activeDrawerId = null;
            $this->activeShiftSummary = null;
            $this->dispatch('close-shift-printed', report: $summary);
            $activeDrawer = null;
        }

        if (! $activeDrawer) {
            $drawer = $service->openShift(0, $userId);
            $this->activeDrawerId = $drawer->id;

            $openSlipPayload = [
                'type' => 'open_shift',
                'shop_name' => $this->about?->shop_name ?? 'Mataverse POS',
                'shop_location' => $this->about?->shop_location,
                'drawer_id' => $drawer->id,
                'cashier_name' => pos_user()?->name ?? auth()->user()?->name ?? 'Kasir',
                'opened_at' => now()->format('d/m/Y H:i'),
            ];

            $this->dispatch('open-shift-printed', slip: $openSlipPayload);
        }

        $this->showOpenStoreModal = false;
        $this->hasStaleOpenDrawer = false;
        $this->staleDrawerOpenedAt = null;

        Notification::make()
            ->title(__('Toko Berhasil Dibuka'))
            ->body(__('Sesi kasir hari ini (Shift #:id) telah aktif dan laporan buka toko sedang dicetak.', ['id' => $this->activeDrawerId]))
            ->success()
            ->send();
    }

    public function openShiftModal(): void
    {
        $this->submitOpenShift();
    }

    public function submitOpenShift(): void
    {
        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (User::hasConfiguredAdminPin($authUser) && method_exists($this, 'requestAdminPin')) {
            $this->requestAdminPin('open_shift');

            return;
        }

        $this->executeOpenShift();
    }

    public function executeOpenShift(): void
    {
        $service = app(ShiftReportService::class);

        $activeDrawer = $service->getActiveDrawer();
        if ($activeDrawer) {
            Notification::make()
                ->title(__('Kasir Sudah Dibuka'))
                ->body(__('Shift kasir saat ini masih aktif.'))
                ->warning()
                ->send();

            return;
        }

        $userId = pos_user_id() ?? auth()->id() ?? 1;

        $drawer = $service->openShift(0, (int) $userId);
        $this->activeDrawerId = $drawer->id;

        $openSlipPayload = [
            'type' => 'open_shift',
            'shop_name' => $this->about?->shop_name ?? 'Mataverse POS',
            'shop_location' => $this->about?->shop_location,
            'drawer_id' => $drawer->id,
            'cashier_name' => pos_user()?->name ?? auth()->user()?->name ?? 'Kasir',
            'opened_at' => now()->format('d/m/Y H:i'),
        ];

        $this->dispatch('open-shift-printed', slip: $openSlipPayload);

        Notification::make()
            ->title(__('Buka Kasir Berhasil'))
            ->body(__('Sesi kasir baru (Shift #:id) telah aktif.', ['id' => $drawer->id]))
            ->success()
            ->send();
    }

    public function openCloseShiftModal(): void
    {
        $service = app(ShiftReportService::class);
        $drawer = $this->activeDrawerId ? CashDrawer::find($this->activeDrawerId) : $service->getActiveDrawer();

        if (! $drawer) {
            Notification::make()
                ->title(__('Belum Ada Shift Terbuka'))
                ->body(__('Silakan buka kasir terlebih dahulu sebelum menutup shift.'))
                ->warning()
                ->send();

            return;
        }

        $this->activeShiftSummary = $service->getShiftSummary($drawer);
        $this->dispatch('open-modal', id: 'close-shift-modal');
    }

    public function submitCloseShift(): void
    {
        /** @var ?User $authUser */
        $authUser = Filament::auth()->user();

        if (User::hasConfiguredAdminPin($authUser) && method_exists($this, 'requestAdminPin')) {
            $this->requestAdminPin('close_shift');

            return;
        }

        $this->executeCloseShift();
    }

    public function executeCloseShift(): void
    {
        $service = app(ShiftReportService::class);
        $drawer = $this->activeDrawerId ? CashDrawer::find($this->activeDrawerId) : $service->getActiveDrawer();

        if (! $drawer) {
            Notification::make()
                ->title(__('Shift Tidak Ditemukan'))
                ->danger()
                ->send();

            return;
        }

        $userId = pos_user_id() ?? auth()->id() ?? 1;
        $summary = $service->getShiftSummary($drawer);
        $service->closeShift($drawer, (int) $userId);

        $this->activeDrawerId = null;
        $this->activeShiftSummary = null;

        $this->dispatch('close-modal', id: 'close-shift-modal');

        $this->dispatch('close-shift-printed', report: $summary);

        Notification::make()
            ->title(__('Tutup Kasir Berhasil'))
            ->body(__('Laporan shift tutup kasir telah dicetak dan shift berhasil ditutup.'))
            ->success()
            ->send();
    }

    public function printCurrentShift(): void
    {
        $service = app(ShiftReportService::class);
        $drawer = $this->activeDrawerId ? CashDrawer::find($this->activeDrawerId) : $service->getActiveDrawer();

        if (! $drawer) {
            Notification::make()
                ->title(__('Belum Ada Shift Terbuka'))
                ->warning()
                ->send();

            return;
        }

        $summary = $service->getShiftSummary($drawer);
        $summary['is_temporary'] = true;

        $this->dispatch('close-shift-printed', report: $summary);

        Notification::make()
            ->title(__('Laporan Shift Dikirim ke Printer'))
            ->body(__('Rekap item terjual shift sementara berhasil dicetak.'))
            ->success()
            ->send();
    }
}
