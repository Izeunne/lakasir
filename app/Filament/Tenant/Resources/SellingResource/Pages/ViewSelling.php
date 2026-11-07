<?php

namespace App\Filament\Tenant\Resources\SellingResource\Pages;

use App\Features\PrintSellingA5;
use App\Filament\Tenant\Pages\StorePin;
use App\Filament\Tenant\Resources\SellingDetailResource\RelationManagers\SellingDetailsRelationManager;
use App\Filament\Tenant\Resources\SellingResource;
use App\Models\Tenants\About;
use App\Models\Tenants\Selling;
use App\Models\Tenants\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;

class ViewSelling extends ViewRecord
{
    protected static string $resource = SellingResource::class;

    public ?About $about = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->about = About::first();
    }

    public function getTitle(): string|Htmlable
    {
        return 'View '.$this->getRecord()->code;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_note')
                ->label(__('Note Penjualan'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(function (): bool {
                    /** @var ?User $user */
                    $user = Filament::auth()->user();

                    return $user ? $user->canEditSellingNote() : false;
                })
                ->fillForm(fn (): array => [
                    'note' => $this->getRecord()->note,
                ])
                ->form([
                    Textarea::make('note')
                        ->label(__('Note Penjualan'))
                        ->rows(3)
                        ->placeholder(__('Isi catatan untuk penjualan ini...')),
                ])
                ->action(function (array $data): void {
                    $this->getRecord()->update([
                        'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
                    ]);

                    Notification::make()
                        ->title(__('Note Penjualan Disimpan'))
                        ->success()
                        ->send();
                }),
            Action::make('delete_with_pin')
                ->label(__('Hapus Penjualan'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading(__('Hapus Riwayat Penjualan'))
                ->modalDescription(__('Masukkan PIN Toko untuk menghapus riwayat penjualan ini. Stok produk pada transaksi ini akan dikembalikan.'))
                ->modalSubmitActionLabel(__('Verifikasi PIN & Hapus'))
                ->visible(function (): bool {
                    /** @var ?User $user */
                    $user = Filament::auth()->user();

                    return $user ? $user->canManageStorePin() : false;
                })
                ->form([
                    TextInput::make('pin')
                        ->label(__('PIN Toko'))
                        ->password()
                        ->revealable()
                        ->numeric()
                        ->required()
                        ->placeholder(__('Masukkan PIN Toko')),
                ])
                ->action(function (array $data): void {
                    if (! StorePin::isPinConfigured()) {
                        Notification::make()
                            ->title(__('PIN Toko Belum Diatur'))
                            ->body(__('Silakan atur PIN Toko terlebih dahulu di menu PIN Toko sebelum menghapus penjualan.'))
                            ->danger()
                            ->send();
                        return;
                    }

                    if (! StorePin::verify($data['pin'] ?? null)) {
                        Notification::make()
                            ->title(__('PIN Toko Salah'))
                            ->body(__('PIN Toko yang Anda masukkan tidak sesuai. Penghapusan dibatalkan.'))
                            ->danger()
                            ->send();
                        return;
                    }

                    SellingResource::deleteSellingAndRestoreStock($this->getRecord());

                    Notification::make()
                        ->title(__('Riwayat Penjualan Dihapus'))
                        ->body(__('Data penjualan berhasil dihapus dan stok produk telah dikembalikan.'))
                        ->success()
                        ->send();

                    $this->redirect(SellingResource::getUrl('index'));
                }),
            Action::make(__('Print invoice'))
                ->icon('heroicon-s-printer')
                ->extraAttributes([
                    'id' => 'printInvoice',
                ])
                ->color(Color::Teal)
                ->visible(can('can print selling') && feature(PrintSellingA5::class)),
            Action::make(__('Print receipt'))
                ->icon('heroicon-s-printer')
                ->extraAttributes([
                    'id' => 'printButton',
                ])
                ->visible(can('can print selling')),
        ];
    }

    public function getView(): string
    {
        return 'filament.tenant.resources.sellings.pages.view-selling';
    }

    public function getRecord(): Selling
    {
        return $this->record->load('sellingDetails.product');
    }

    public function getRelationManagers(): array
    {
        return [
            SellingDetailsRelationManager::make(),
        ];
    }
}
