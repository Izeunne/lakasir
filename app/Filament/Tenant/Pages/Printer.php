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
        $connected = MobilePrinter::query()
            ->where('is_connected', true)
            ->orderBy('device_name')
            ->get()
            ->mapWithKeys(function (MobilePrinter $mp) {
                return [$mp->device_id => '📱 ' . $mp->display_label];
            })
            ->toArray();

        $connected['virtual_simulator'] = '🖥️ Virtual Thermal Simulator (Preview Layar Tanpa Printer Fisik)';

        return $connected;
    }

    /**
     * Shared form schema for creating/editing a printer station.
     */
    protected function getPrinterFormSchema(): array
    {
        return [
            Components\Section::make(__('Identitas & Perangkat Printer'))
                ->description(__('Pilih perangkat printer yang sudah terhubung di Aplikasi Mobile Lakasir.'))
                ->columns(2)
                ->schema([
                    Components\TextInput::make('name')
                        ->label(__('Nama Station Printer'))
                        ->placeholder(__('Contoh: Printer Kasir, Printer Bar, Printer Dapur'))
                        ->required()
                        ->maxLength(100),

                    Components\Select::make('device_id')
                        ->label(__('Perangkat Printer (Terkoneksi di Mobile)'))
                        ->options(fn () => self::getConnectedMobilePrinterOptions())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->required()
                        ->live()
                        ->helperText(__('Hanya menampilkan printer yang berstatus Terhubung (Connected) di Aplikasi Mobile.'))
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if ($state === 'virtual_simulator') {
                                $set('device_name', 'Virtual Printer (Preview Layar)');
                                $set('connection_type', 'virtual');
                                $set('driver', 'virtual');
                                return;
                            }

                            $mobile = MobilePrinter::where('device_id', $state)->first();
                            if ($mobile) {
                                $set('device_name', $mobile->device_name);
                                $set('connection_type', $mobile->connection_type);
                                $set('driver', $mobile->connection_type);
                                if ($mobile->paper_width) {
                                    $set('paper_width', $mobile->paper_width);
                                }
                            }
                        }),

                    Components\Hidden::make('device_name')
                        ->default('Virtual Printer (Preview Layar)'),

                    Components\Hidden::make('connection_type')
                        ->default('bluetooth'),

                    Components\Hidden::make('driver')
                        ->default('bluetooth'),

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
                    ->label(__('Perangkat Mobile'))
                    ->formatStateUsing(function (PrinterModel $record): string {
                        if ($record->device_id === 'virtual_simulator' || $record->connection_type === 'virtual') {
                            return '🖥️ Virtual Simulator (Layar)';
                        }
                        $type = strtoupper($record->connection_type ?: 'BT');
                        $name = $record->device_name ?: $record->device_id;

                        return "[{$type}] {$name}";
                    })
                    ->description(function (PrinterModel $record): string {
                        if ($record->device_id === 'virtual_simulator' || $record->connection_type === 'virtual') {
                            return __('Mode Preview Layar Browser');
                        }
                        $mobile = $record->mobilePrinter;
                        if ($mobile && $mobile->is_connected) {
                            $seen = $mobile->last_seen_at ? $mobile->last_seen_at->diffForHumans() : __('Baru saja');

                            return "🟢 Terhubung di Mobile ({$seen})";
                        }

                        return '🔴 Belum Terhubung di Mobile (' . ($record->device_id ?: '-') . ')';
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
        $deviceId = $data['device_id'] ?? null;
        if ($deviceId === 'virtual_simulator') {
            $data['device_name'] = 'Virtual Printer (Preview Layar)';
            $data['connection_type'] = 'virtual';
            $data['driver'] = 'virtual';
        } elseif ($deviceId) {
            $mobile = MobilePrinter::where('device_id', $deviceId)->first();
            if ($mobile) {
                $data['device_name'] = $mobile->device_name;
                $data['connection_type'] = $mobile->connection_type;
                $data['driver'] = $mobile->connection_type;
            }
        }

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
     * Simulate / Register mobile connected printers for testing or manual bridge pairing.
     */
    public function simulateMobilePrinters(): void
    {
        $samplePrinters = [
            [
                'device_id' => 'BT-KASIR-001',
                'device_name' => 'RPP02N Thermal Kasir',
                'connection_type' => 'bluetooth',
                'address' => 'DC:0D:30:A1:10:01',
                'paper_width' => '58mm',
                'is_connected' => true,
            ],
            [
                'device_id' => 'LAN-BAR-002',
                'device_name' => 'Epson TM-T82 Bar',
                'connection_type' => 'lan',
                'address' => '192.168.1.201:9100',
                'paper_width' => '58mm',
                'is_connected' => true,
            ],
            [
                'device_id' => 'LAN-DAPUR-003',
                'device_name' => 'Epson TM-U220 Dapur',
                'connection_type' => 'lan',
                'address' => '192.168.1.202:9100',
                'paper_width' => '80mm',
                'is_connected' => true,
            ],
        ];

        PrintBridgeService::syncMobilePrinters($samplePrinters, 1, 'Android POS Tablet', false);

        Notification::make()
            ->title(__('Printer Mobile Terdeteksi'))
            ->body(__('3 perangkat printer dari Aplikasi Mobile (Kasir BT, Bar LAN, Dapur LAN) aktif dan siap dipilih.'))
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
            'header' => $p->header,
            'footer' => $p->footer,
            'copies' => $p->copies,
        ])->values()->toArray();

        $this->dispatch('printers-config-updated', printers: $printers);
    }
}
