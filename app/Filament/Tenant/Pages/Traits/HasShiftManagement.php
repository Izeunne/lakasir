<?php

namespace App\Filament\Tenant\Pages\Traits;

use App\Models\Tenants\CashDrawer;
use App\Services\Tenants\ShiftReportService;
use Filament\Notifications\Notification;

trait HasShiftManagement
{
    public ?int $activeDrawerId = null;
    public float|string $startingCashInput = 0;
    public ?array $activeShiftSummary = null;

    public function initializeShift(): void
    {
        $service = app(ShiftReportService::class);
        $drawer = $service->getActiveDrawer();
        $this->activeDrawerId = $drawer?->id;
    }

    public function openShiftModal(): void
    {
        $this->startingCashInput = 0;
        $this->dispatch('open-modal', id: 'open-shift-modal');
    }

    public function submitOpenShift(): void
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
        $startingCash = (float) str_replace(',', '', (string) $this->startingCashInput);

        $drawer = $service->openShift($startingCash, (int) $userId);
        $this->activeDrawerId = $drawer->id;

        $this->dispatch('close-modal', id: 'open-shift-modal');

        $openSlipPayload = [
            'type' => 'open_shift',
            'shop_name' => $this->about?->shop_name ?? 'Lakasir POS',
            'shop_location' => $this->about?->shop_location,
            'drawer_id' => $drawer->id,
            'cashier_name' => pos_user()?->name ?? auth()->user()?->name ?? 'Kasir',
            'opened_at' => now()->format('d/m/Y H:i'),
            'starting_cash' => $startingCash,
        ];

        $this->dispatch('open-shift-printed', slip: $openSlipPayload);

        Notification::make()
            ->title(__('Buka Kasir Berhasil'))
            ->body(__('Sesi shift baru telah dibuka dengan modal awal :cash.', ['cash' => price_format($startingCash)]))
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
