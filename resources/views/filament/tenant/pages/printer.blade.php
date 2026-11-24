<x-filament-panels::page>
  @php
    $connectedMobilePrinters = \App\Models\Tenants\MobilePrinter::where('is_connected', true)->orderBy('device_name')->get();
    $activeStations = \App\Models\Tenants\Printer::active()->orderBy('id')->get();
  @endphp

  {{-- Status Card Integrasi Mobile Print Bridge --}}
  <div class="rounded-xl border border-gray-200/90 bg-white p-4 shadow-2xs dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-lakasir-primary/10 text-lakasir-primary">
          <x-heroicon-o-device-phone-mobile class="h-5 w-5" />
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white">
              {{ __('Mataverse Mobile Print Bridge') }}
            </h3>
            @if ($connectedMobilePrinters->isNotEmpty())
              <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 dark:bg-success-950/40 px-2 py-0.5 text-[11px] font-bold text-success-700 dark:text-success-400">
                <span class="h-1.5 w-1.5 rounded-full bg-success-500 animate-pulse"></span>
                {{ $connectedMobilePrinters->count() }} {{ __('Printer Mobile Terhubung') }}
              </span>
            @else
              <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                ● {{ __('Standby / Menunggu Aplikasi Mobile') }}
              </span>
            @endif
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            {{ __('Mendukung printer thermal Bluetooth, LAN Network, dan USB langsung dari HP/Tablet Android atau browser.') }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <x-filament::button
          wire:click="refreshMobilePrinters"
          color="gray"
          size="sm"
          icon="heroicon-o-arrow-path"
          onclick="syncMobileBridgePrinters()">
          {{ __('Sinkron Mobile') }}
        </x-filament::button>

        <x-filament::button
          tag="a"
          href="/print-client.html"
          target="_blank"
          color="gray"
          size="sm"
          icon="heroicon-o-qr-code">
          {{ __('Setup & QR') }}
        </x-filament::button>
      </div>
    </div>

    @if ($connectedMobilePrinters->isNotEmpty())
      <div class="mt-3.5 pt-3 border-t border-gray-100 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
        @foreach ($connectedMobilePrinters as $mp)
          @php
            $typeLabel = match (strtolower($mp->connection_type ?? '')) {
              'bluetooth' => 'Bluetooth',
              'lan', 'network', 'ethernet' => 'LAN',
              'usb' => 'USB',
              default => strtoupper($mp->connection_type ?: 'Printer'),
            };
          @endphp
          <div class="flex items-center justify-between rounded-lg border border-gray-200/80 bg-gray-50/70 px-3 py-2 text-xs dark:border-gray-700/60 dark:bg-gray-800/50">
            <div class="min-w-0 pr-2">
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-gray-900 dark:text-white truncate">{{ $mp->device_name }}</span>
                <span class="rounded bg-gray-200/80 px-1 py-0.2 text-[10px] font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $typeLabel }}</span>
              </div>
              <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono truncate">{{ $mp->device_id }} • {{ $mp->paper_width }}</p>
            </div>
            <span class="inline-flex h-2 w-2 rounded-full bg-success-500 shrink-0" title="Online"></span>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- Tabel Manajemen Station Multi-Printer --}}
  <div>
    {{ $this->table }}
  </div>
</x-filament-panels::page>

@script
<script>
  // Simpan daftar printer aktif ke localStorage untuk akses instan di halaman Kasir
  const initialPrinters = @js($activeStations->map(fn ($p) => [
    'id' => $p->id,
    'name' => $p->name,
    'printerId' => $p->device_id,
    'device_id' => $p->device_id,
    'device_name' => $p->device_name,
    'driver' => $p->connection_type,
    'paper_width' => $p->paper_width,
    'print_all_categories' => (bool) $p->print_all_categories,
    'category_ids' => array_map('intval', $p->category_ids ?? []),
    'show_price' => (bool) $p->show_price,
    'show_header' => (bool) ($p->show_header ?? true),
    'header' => $p->header,
    'footer' => $p->footer,
    'copies' => (int) $p->copies,
  ])->values());

  if (initialPrinters && initialPrinters.length > 0) {
    localStorage.setItem('printers_multi', JSON.stringify(initialPrinters));
    localStorage.setItem('printer', JSON.stringify(initialPrinters[0]));
  }

  $wire.on('printers-config-updated', ({ printers }) => {
    if (Array.isArray(printers)) {
      localStorage.setItem('printers_multi', JSON.stringify(printers));
      if (printers.length > 0) {
        localStorage.setItem('printer', JSON.stringify(printers[0]));
      }
    }
  });

  // Fungsi sinkronisasi langsung jika dibuka di dalam WebView Android LakasirBridge
  window.syncMobileBridgePrinters = function () {
    if (window.LakasirBridge && typeof window.LakasirBridge.getConnectedPrinters === 'function') {
      try {
        const raw = window.LakasirBridge.getConnectedPrinters();
        const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
        if (Array.isArray(parsed) && parsed.length > 0) {
          $wire.syncFromMobileBridge(parsed, 'Mataverse Android WebView');
        }
      } catch (e) {
        console.warn('Gagal membaca getConnectedPrinters dari LakasirBridge:', e);
      }
    }
  };

  // Jalankan auto-detect saat halaman dibuka di Android WebView
  if (window.LakasirBridge) {
    window.syncMobileBridgePrinters();
  }

  // Handler Test Cetak per Station (mendukung Virtual Simulator & Direct Bridge)
  $wire.on('trigger-station-test-print', async (eventData) => {
    const payload = Array.isArray(eventData) ? eventData[0] : eventData;
    if (!payload || !payload.printer) return;

    const st = payload.printer;
    const paperWidth = st.paper_width || '58mm';
    const divider = paperWidth === '80mm'
      ? '------------------------------------------------'
      : '-------------------------------';

    try {
      const targetId = st.device_id || st.name || 'kasir';

      const printer = new Printer(targetId, paperWidth, st.connection_type || st.driver);
      let action = printer.font('a').align('center');

      if (st.show_price) {
        // Format Struk dengan Harga (Kasir)
        action.size(1)
          .text(st.name.toUpperCase())
          .size(0)
          .text(st.header || 'Struk Pembayaran Kasir')
          .align('left')
          .text(divider)
          .table(['Station', st.name])
          .table(['Perangkat', st.device_name || st.device_id])
          .table(['Kategori', st.categories || 'Semua Kategori'])
          .table(['Tampil Harga', 'YA (Dengan Harga)'])
          .table(['Waktu', new Date().toLocaleTimeString()])
          .text(divider)
          .table(['Nasi Goreng Spesial', '25.000 x 1'])
          .align('right')
          .text('25.000')
          .align('left')
          .table(['Es Teh Manis', '6.000 x 2'])
          .align('right')
          .text('12.000')
          .align('left')
          .text(divider)
          .table(['TOTAL', 'IDR 37.000'])
          .text(divider)
          .align('center')
          .text(st.footer || 'Terima Kasih');
      } else {
        // Format Nota Checker Tanpa Harga (Dapur / Bar)
        action.size(1)
          .text('*** ' + st.name.toUpperCase() + ' ***')
          .size(0)
          .text(st.header || 'NOTA CHECKER PESANAN')
          .align('left')
          .text(divider)
          .table(['Station', st.name])
          .table(['Kategori', st.categories])
          .table(['Mode Nota', 'TANPA HARGA'])
          .table(['Meja / Tipe', 'Meja 05 (Dine In)'])
          .table(['Waktu', new Date().toLocaleTimeString()])
          .text(divider)
          .text('2x  Contoh Menu (' + (st.categories || 'Pesanan') + ')')
          .text('    - Variasi: Pilihan Standar')
          .text('    * Catatan: Segera disiapkan')
          .text(divider)
          .table(['TOTAL ITEM', '2 Porsi'])
          .align('center')
          .text(st.footer || ('--- ' + st.name.toUpperCase() + ' ---'));
      }

      await action.cut().print(st.device_id || 'kasir');
    } catch (e) {
      console.error('Error station test print:', e);
    }
  });
</script>
@endscript
