<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenants\Setting;
use App\Models\Tenants\User;
use App\Traits\HasTranslatableResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class StorePin extends Page implements HasForms
{
    use HasTranslatableResource,
        InteractsWithFormActions,
        InteractsWithForms;

    public static ?string $label = 'PIN Toko';

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static string $view = 'filament.tenant.pages.store-pin';

    public ?array $data = [];

    public bool $hasExistingPin = false;

    public static function canAccess(): bool
    {
        /** @var ?User $user */
        $user = Filament::auth()->user();

        return $user ? $user->canManageStorePin() : false;
    }

    public static function isPinConfigured(): bool
    {
        return filled(Setting::get('store_pin'));
    }

    public static function verify(?string $inputPin): bool
    {
        if (blank($inputPin)) {
            return false;
        }

        $storedPin = Setting::get('store_pin');
        if (blank($storedPin)) {
            return false;
        }

        if (str_starts_with($storedPin, '$2y$') || str_starts_with($storedPin, '$argon2')) {
            return Hash::check($inputPin, $storedPin);
        }

        return hash_equals((string) $storedPin, (string) $inputPin);
    }

    public function mount(): void
    {
        $this->hasExistingPin = self::isPinConfigured();
        $this->form->fill([]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Pengaturan PIN Keamanan Toko'))
                    ->description(__('PIN Toko hanya dapat diakses oleh Superadmin dan Kepala Toko. Digunakan sebagai verifikasi keamanan saat menghapus riwayat penjualan yang gagal atau dibatalkan.'))
                    ->schema([
                        Placeholder::make('status')
                            ->label(__('Status PIN Toko'))
                            ->content(fn () => new HtmlString(
                                $this->hasExistingPin
                                    ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-success-100 px-3 py-1 text-xs font-bold text-success-700 dark:bg-success-900/40 dark:text-success-400">● '.__('PIN Toko Sudah Aktif').'</span>'
                                    : '<span class="inline-flex items-center gap-1.5 rounded-full bg-warning-100 px-3 py-1 text-xs font-bold text-warning-700 dark:bg-warning-900/40 dark:text-warning-400">● '.__('PIN Toko Belum Diatur').'</span>'
                            )),
                        TextInput::make('current_pin')
                            ->label(__('PIN Toko Saat Ini'))
                            ->password()
                            ->revealable()
                            ->numeric()
                            ->visible(fn () => $this->hasExistingPin)
                            ->required(fn () => $this->hasExistingPin)
                            ->placeholder(__('Masukkan PIN Toko lama')),
                        TextInput::make('pin')
                            ->label($this->hasExistingPin ? __('PIN Toko Baru') : __('Buat PIN Toko'))
                            ->password()
                            ->revealable()
                            ->numeric()
                            ->minLength(4)
                            ->maxLength(8)
                            ->required()
                            ->same('pin_confirmation')
                            ->helperText(__('Gunakan 4 hingga 8 digit angka.'))
                            ->placeholder(__('Contoh: 123456')),
                        TextInput::make('pin_confirmation')
                            ->label(__('Konfirmasi PIN Toko Baru'))
                            ->password()
                            ->revealable()
                            ->numeric()
                            ->required()
                            ->placeholder(__('Ulangi PIN Toko baru')),
                    ])
                    ->columns(1)
                    ->maxWidth('xl'),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Simpan PIN Toko'))
                ->icon('heroicon-o-check')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if ($this->hasExistingPin) {
            if (! self::verify($state['current_pin'] ?? null)) {
                Notification::make()
                    ->title(__('PIN Toko Saat Ini Salah'))
                    ->body(__('PIN Toko lama yang Anda masukkan tidak sesuai.'))
                    ->danger()
                    ->send();
                return;
            }
        }

        Setting::set('store_pin', Hash::make($state['pin']));

        $this->hasExistingPin = true;
        $this->form->fill([]);

        Notification::make()
            ->title(__('PIN Toko Berhasil Disimpan'))
            ->body(__('PIN keamanan toko telah diperbarui dan siap digunakan.'))
            ->success()
            ->send();
    }
}

