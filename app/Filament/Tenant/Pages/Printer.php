<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenants\Category;
use App\Models\Tenants\MobilePrinter;
use App\Models\Tenants\Printer as PrinterModel;
use App\Models\Tenants\Setting;
use App\Services\PrintBridgeService;
use App\Traits\HasTranslatableResource;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class Printer extends Page implements HasActions, HasForms, HasTable
{
    use HasTranslatableResource;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static string $view = 'filament.tenant.pages.printer';

    public static function canAccess(): bool
    {
        return can('access printer');
    }

    public function getTitle(): string
    {
        return __('Manajemen Multi-Printer & Routing Kategori');
    }

    /**
     * Get options of printers that are connected to the Mobile App.
     *
     * @return array<string, string>
     */
    public static function getConnectedMobilePrinterOptions(): array
    {
        return MobilePrinter::query()
            ->where('is_connected', true)
            ->orderBy('device_name')
            ->get()
            ->mapWithKeys(function (MobilePrinter $mp) {
                return [$mp->device_id => '📱 ' . $mp->display_label];
            })
            ->toArray();
    }

    /**
     * Shared form schema for creating/editing a printer station.
     */
    protected function getPrinterFormSchema(): array
    {
        $hasMobilePrinters = MobilePrinter::where('is_connected', true)->exists();

        return [
            Components\Section::make(__('Identitas & Perangkat Printer'))
                ->description(__('Konfigurasi station printer fisik (Bluetooth, LAN / Network, USB, atau via Mobile Bridge).'))
                ->columns(2)
                ->schema([
                    Components\TextInput::make('name')
                        ->label(__('Nama Station Printer'))
                        ->placeholder(__('Contoh: Printer Kasir, Printer Bar, Printer Dapur'))
                        ->required()
                        ->maxLength(100),

                    Components\Select::make('connection_type')
                        ->label(__('Jenis Koneksi Printer'))
                        ->options([
                            'bluetooth' => '📶 Bluetooth Thermal Printer',
                            'lan' => '🌐 LAN / Network Thermal Printer (IP Address)',
                            'usb' => '🔌 USB Thermal Printer (PC Kasir)',
                            'mobile' => '📱 Terhubung via Lakasir Mobile Bridge',
                        ])
                        ->default($hasMobilePrinters ? 'mobile' : 'bluetooth')
                        ->native(false)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set, Get $get) {
                            if ($state === 'lan') {
                                if (! $get('port')) {
                                    $set('port', '9100');
                                }
                            }
                        }),

                    // Jika koneksi via Mobile Bridge
                    Components\Select::make('mobile_device_id')
                        ->label(__('Pilih Printer Mobile Terhubung'))
                        ->options(fn () => self::getConnectedMobilePrinterOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'mobile')
                        ->required(fn (Get $get): bool => $get('connection_type') === 'mobile')
                        ->helperText(fn () => MobilePrinter::where('is_connected', true)->exists()
                            ? __('Pilih printer yang sedang terhubung aktif pada Aplikasi Mobile Lakasir.')
                            : __('Belum ada printer mobile terdeteksi. Hubungkan printer di aplikasi mobile Lakasir terlebih dahulu atau pilih jenis koneksi lain (Bluetooth / LAN / USB).'))
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            $mobile = MobilePrinter::where('device_id', $state)->first();
                            if ($mobile) {
                                $set('device_name', $mobile->device_name);
                                $set('device_id', $mobile->device_id);
                                if ($mobile->paper_width) {
                                    $set('paper_width', $mobile->paper_width);
                                }
                            }
                        }),

                    // Jika koneksi LAN / Network
                    Components\TextInput::make('ip_address')
                        ->label(__('Alamat IP Printer (LAN)'))
                        ->placeholder(__('Contoh: 192.168.1.200'))
                        ->required(fn (Get $get): bool => $get('connection_type') === 'lan')
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'lan')
                        ->helperText(__('Alamat IP lokal printer thermal di jaringan Anda.'))
                        ->columnSpan(1),

                    Components\TextInput::make('port')
                        ->label(__('Port Raw ESC/POS'))
                        ->placeholder('9100')
                        ->default('9100')
                        ->required(fn (Get $get): bool => $get('connection_type') === 'lan')
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'lan')
                        ->columnSpan(1),

                    // Jika Bluetooth
                    Components\TextInput::make('bt_address')
                        ->label(__('Nama / MAC Address Bluetooth'))
                        ->placeholder(__('Contoh: RPP02N atau DC:0D:30:A1:10:01'))
                        ->required(fn (Get $get): bool => $get('connection_type') === 'bluetooth')
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'bluetooth')
                        ->helperText(__('Nama perangkat Bluetooth atau MAC Address printer.'))
                        ->columnSpan(1),

                    // Nama Perangkat Printer
                    Components\TextInput::make('device_name')
                        ->label(__('Model / Nama Perangkat Printer'))
                        ->placeholder(__('Contoh: Epson TM-T82, RPP02N, Thermal 58mm'))
                        ->required()
                        ->columnSpan(fn (Get $get): bool => in_array($get('connection_type'), ['bluetooth', 'usb'])),

                    // ID / Vendor ID untuk USB
                    Components\TextInput::make('device_id')
                        ->label(__('ID Perangkat USB'))
                        ->default('usb')
                        ->placeholder(__('Contoh: usb atau Vendor ID'))
                        ->visible(fn (Get $get): bool => $get('connection_type') === 'usb')
                        ->required(fn (Get $get): bool => $get('connection_type') === 'usb')
                        ->columnSpan(1),

                    Components\Select::make('paper_width')
                        ->label(__('Ukuran Lebar Kertas'))
                        ->options([
                            '58mm' => '58 mm (Standar)',
                            '80mm' => '80 mm (Lebar)',
                        ])
                        ->default('58mm')
                        ->native(false)
                        ->required(),

                    Components\TextInput::make('copies')
                        ->label(__('Jumlah Rangkap Cetak (Copy)'))
                        ->helperText(__('Atur 2 rangkap jika ingin 1 lembar untuk Pelanggan dan 1 lembar Salinan Toko/Disimpan.'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(5)
                        ->default(1)
                        ->required(),
                ]),

            Components\Section::make(__('Routing Kategori & Format Nota'))
                ->description(__('Tentukan kategori menu apa saja yang dicetak oleh printer ini dan apakah harga ditampilkan.'))
                ->columns(2)
                ->schema([
                    Components\Toggle::make('print_all_categories')
                        ->label(__('Cetak Semua Kategori Menu'))
                        ->helperText(__('Aktifkan untuk Printer Kasir agar semua pesanan tercetak. Matikan untuk memilih kategori spesifik (misal khusus Bar atau Dapur).'))
                        ->default(true)
                        ->live()
                        ->columnSpan(1),

                    Components\Toggle::make('show_price')
                        ->label(__('Tampilkan Harga di Nota'))
                        ->helperText(__('Aktifkan untuk Struk Tagihan Kasir. Matikan untuk Nota Checker (Dapur/Bar) agar hanya mencetak Nama Menu, Qty, Variasi & Catatan tanpa harga.'))
                        ->default(true)
                        ->columnSpan(1),

                    Components\Toggle::make('show_header')
                        ->label(__('Cetak Header & Identitas Toko (Logo, Nama, Alamat, NPWP)'))
                        ->helperText(__('Aktifkan untuk Struk Kasir Pelanggan. Matikan jika tidak memerlukan kop identitas (misal Salinan Toko / Backup Kasir / Dapur / Bar) agar lebih hemat kertas.'))
                        ->default(true)
                        ->columnSpanFull(),

                    Components\CheckboxList::make('category_ids')
                        ->label(__('Pilih Kategori Menu yang Dicetak oleh Printer Ini'))
                        ->options(fn () => Category::orderBy('name')->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->gridDirection('row')
                        ->bulkToggleable()
                        ->visible(fn (Get $get): bool => ! (bool) $get('print_all_categories'))
                        ->required(fn (Get $get): bool => ! (bool) $get('print_all_categories'))
                        ->helperText(__('Jika transaksi tidak memiliki item dari kategori yang dicentang di atas, printer ini otomatis tidak akan mencetak.'))
                        ->columnSpanFull(),
                ]),

            Components\Section::make(__('Header, Footer & Status'))
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema([
                    Components\Textarea::make('header')
                        ->label(__('Teks Header Tambahan'))
                        ->placeholder(__('Contoh: *** NOTA PESANAN DAPUR *** atau alamat cabang'))
                        ->rows(2),

                    Components\Textarea::make('footer')
                        ->label(__('Teks Footer Tambahan'))
                        ->placeholder(__('Contoh: Terima kasih atas kunjungan Anda'))
                        ->rows(2),

                    Components\Toggle::make('is_active')
                        ->label(__('Printer Aktif'))
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PrinterModel::query()->orderBy('id'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Station'))
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (PrinterModel $record): string => $record->header ?: ($record->show_price ? __('Struk Kasir / Tagihan') : __('Nota Checker Pesanan'))),

                Tables\Columns\TextColumn::make('device_name')
                    ->label(__('Perangkat Printer'))
                    ->formatStateUsing(function (PrinterModel $record): string {
                        $type = strtoupper($record->connection_type ?: 'BT');
                        $name = $record->device_name ?: $record->device_id;

                        return "[{$type}] {$name}";
                    })
                    ->description(function (PrinterModel $record): string {
                        $mobile = $record->mobilePrinter;
                        if ($mobile && $mobile->is_connected) {
                            $seen = $mobile->last_seen_at ? $mobile->last_seen_at->diffForHumans() : __('Baru saja');

                            return "🟢 Terhubung di Mobile ({$seen})";
                        }

                        if ($record->connection_type === 'lan') {
                            return '🌐 IP: ' . ($record->device_id ?: '-');
                        }

                        if ($record->connection_type === 'usb') {
                            return '🔌 USB Port: ' . ($record->device_id ?: 'Default USB');
                        }

                        return '📶 ID/MAC: ' . ($record->device_id ?: '-');
                    }),

                Tables\Columns\TextColumn::make('categories_display')
                    ->label(__('Kategori yang Dicetak'))
                    ->badge()
                    ->state(fn (PrinterModel $record): array => $record->category_names)
                    ->color(fn (PrinterModel $record): string => $record->print_all_categories ? 'primary' : 'info'),

                Tables\Columns\TextColumn::make('show_price')
                    ->label(__('Format Harga'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Dengan Harga') : __('Tanpa Harga (Checker)'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-banknotes' : 'heroicon-m-clipboard-document-list'),

                Tables\Columns\TextColumn::make('paper_width')
                    ->label(__('Kertas'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (PrinterModel $record): string => "{$record->paper_width} ({$record->copies}x)"),

                Tables\Columns\ToggleColumn::make('show_header')
                    ->label(__('Kop Toko')),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label(__('Aktif')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make('create_printer')
                    ->label(__('Tambah Printer'))
                    ->icon('heroicon-o-plus')
                    ->model(PrinterModel::class)
                    ->modalHeading(__('Tambah Station Printer Baru'))
                    ->modalWidth('3xl')
                    ->form($this->getPrinterFormSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->SyncDeviceMeta($data);
                    })
                    ->after(function () {
                        $this->syncFrontendPrinters();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('test_print')
                    ->label(__('Test Cetak'))
                    ->icon('heroicon-o-printer')
                    ->color('warning')
                    ->button()
                    ->size('xs')
                    ->action(function (PrinterModel $record) {
                        $job = PrintBridgeService::queuePrinterTest($record);

                        $this->dispatch('trigger-station-test-print', [
                            'printer' => [
                                'id' => $record->id,
                                'name' => $record->name,
                                'device_id' => $record->device_id,
                                'device_name' => $record->device_name,
                                'connection_type' => $record->connection_type,
                                'paper_width' => $record->paper_width,
                                'show_price' => $record->show_price,
                                'show_header' => (bool) ($record->show_header ?? true),
                                'copies' => (int) ($record->copies ?: 1),
                                'categories' => implode(', ', $record->category_names),
                                'header' => $record->header,
                                'footer' => $record->footer,
                            ],
                            'job_id' => $job->id,
                        ]);

                        Notification::make()
                            ->title(__('Test Cetak Dikirim ke :name', ['name' => $record->name]))
                            ->body(__('Format: :format | Kategori: :cat', [
                                'format' => $record->show_price ? __('Dengan Harga') : __('Tanpa Harga (Checker)'),
                                'cat' => implode(', ', $record->category_names),
                            ]))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->modalHeading(__('Edit Konfigurasi Printer'))
                    ->modalWidth('3xl')
                    ->form($this->getPrinterFormSchema())
                    ->mutateRecordDataUsing(function (array $data): array {
                        if (($data['connection_type'] ?? '') === 'lan') {
                            if (!empty($data['device_id']) && str_contains($data['device_id'], ':')) {
                                [$ip, $port] = explode(':', $data['device_id'], 2);
                                $data['ip_address'] = $data['ip_address'] ?? $ip;
                                $data['port'] = $data['port'] ?? $port;
                            }
                        } elseif (($data['connection_type'] ?? '') === 'bluetooth') {
                            $data['bt_address'] = $data['device_id'] ?? '';
                        } elseif (MobilePrinter::where('device_id', $data['device_id'] ?? '')->exists()) {
                            $data['connection_type'] = 'mobile';
                            $data['mobile_device_id'] = $data['device_id'];
                        }

                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        return $this->SyncDeviceMeta($data);
                    })
                    ->after(function () {
                        $this->syncFrontendPrinters();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function () {
                        $this->syncFrontendPrinters();
                    }),
            ])
            ->emptyStateHeading(__('Belum Ada Station Printer'))
            ->emptyStateDescription(__('Tambahkan printer untuk Kasir, Bar, atau Dapur dan atur kategori menu yang akan dicetak oleh masing-masing printer.'));
    }

    protected function SyncDeviceMeta(array $data): array
    {
        $connType = $data['connection_type'] ?? 'bluetooth';

        if ($connType === 'mobile') {
            $mobileId = $data['mobile_device_id'] ?? $data['device_id'] ?? null;
            $mobile = MobilePrinter::where('device_id', $mobileId)->first();
            $data['device_id'] = $mobileId;
            $data['device_name'] = $mobile?->device_name ?? ($data['device_name'] ?? $mobileId);
            $data['connection_type'] = $mobile?->connection_type ?? 'bluetooth';
            $data['driver'] = $mobile?->connection_type ?? 'bluetooth';
        } elseif ($connType === 'lan') {
            $ip = trim($data['ip_address'] ?? '');
            $port = trim($data['port'] ?? '9100') ?: '9100';
            $data['ip_address'] = $ip;
            $data['port'] = $port;
            $data['device_id'] = $ip ? "{$ip}:{$port}" : ($data['device_id'] ?? 'lan');
            $data['device_name'] = !empty($data['device_name']) ? $data['device_name'] : "LAN Printer ({$ip})";
            $data['driver'] = 'lan';
            $data['connection_type'] = 'lan';
        } elseif ($connType === 'bluetooth') {
            $bt = trim($data['bt_address'] ?? '');
            $data['device_id'] = $bt ?: ($data['device_id'] ?? 'bluetooth');
            $data['device_name'] = !empty($data['device_name']) ? $data['device_name'] : "Bluetooth ({$data['device_id']})";
            $data['driver'] = 'bluetooth';
            $data['connection_type'] = 'bluetooth';
        } elseif ($connType === 'usb') {
            $usb = trim($data['device_id'] ?? 'usb') ?: 'usb';
            $data['device_id'] = $usb;
            $data['device_name'] = !empty($data['device_name']) ? $data['device_name'] : 'USB Printer';
            $data['driver'] = 'usb';
            $data['connection_type'] = 'usb';
        }

        unset($data['mobile_device_id'], $data['bt_address']);

        if (!empty($data['print_all_categories'])) {
            $data['category_ids'] = [];
        } else {
            $data['category_ids'] = array_values(array_map('intval', $data['category_ids'] ?? []));
        }

        if (!empty($data['paper_width'])) {
            Setting::set('printer_paper_width', $data['paper_width']);
        }

        return $data;
    }

    /**
     * Called from JS when Android WebView LakasirBridge reports connected printers.
     */
    public function syncFromMobileBridge(array $printers, ?string $deviceModel = 'Lakasir Android App'): void
    {
        $synced = PrintBridgeService::syncMobilePrinters($printers, 1, $deviceModel, true);

        Notification::make()
            ->title(__('Sinkronisasi Mobile Berhasil'))
            ->body(__(':count printer terhubung dari Aplikasi Mobile berhasil dideteksi.', ['count' => $synced->count()]))
            ->success()
            ->send();
    }

    /**
     * Refresh status of connected mobile printers.
     */
    public function refreshMobilePrinters(): void
    {
        $count = MobilePrinter::where('is_connected', true)->count();
        if ($count > 0) {
            Notification::make()
                ->title(__('Sinkronisasi Printer Mobile'))
                ->body(__(':count printer aktif terdeteksi dari Aplikasi Mobile.', ['count' => $count]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('Tidak Ada Printer Mobile Baru'))
                ->body(__('Belum ada printer yang terhubung dari Aplikasi Mobile Lakasir. Pastikan printer telah terhubung pada aplikasi mobile Lakasir.'))
                ->info()
                ->send();
        }
    }

    /**
     * Clear disconnected mobile printers.
     */
    public function clearDisconnectedPrinters(): void
    {
        $deleted = MobilePrinter::where('is_connected', false)->delete();

        Notification::make()
            ->title(__('Daftar Printer Mobile Diperbarui'))
            ->body(__(':count printer terputus berhasil dibersihkan.', ['count' => $deleted]))
            ->success()
            ->send();
    }

    protected function syncFrontendPrinters(): void
    {
        $printers = PrinterModel::active()->orderBy('id')->get()->map(fn (PrinterModel $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'printerId' => $p->device_id,
            'device_id' => $p->device_id,
            'device_name' => $p->device_name,
            'driver' => $p->connection_type,
            'paper_width' => $p->paper_width,
            'print_all_categories' => $p->print_all_categories,
            'category_ids' => array_map('intval', $p->category_ids ?? []),
            'show_price' => $p->show_price,
            'show_header' => (bool) ($p->show_header ?? true),
            'header' => $p->header,
            'footer' => $p->footer,
            'copies' => $p->copies,
        ])->values()->toArray();

        $this->dispatch('printers-config-updated', printers: $printers);
    }
}
