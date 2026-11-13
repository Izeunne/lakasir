<x-filament-panels::page>
  @php
    $connectedMobilePrinters = \App\Models\Tenants\MobilePrinter::where('is_connected', true)->orderBy('device_name')->get();
    $activeStations = \App\Models\Tenants\Printer::active()->orderBy('id')->get();
  @endphp

  {{-- Banner Status Integrasi Aplikasi Mobile & Printer Terdeteksi --}}
  <div class="rounded-2xl border border-indigo-200 dark:border-indigo-900/60 bg-gradient-to-br from-indigo-50/90 via-white to-emerald-50/50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 p-5 shadow-sm space-y-4">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            Print Bridge &amp; Multi-Printer
          </span>
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
            Mendukung Printer Fisik Bluetooth, LAN Network, USB, dan Aplikasi Mobile Lakasir
          </span>
        </div>
        <h2 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white">
          📱 Perangkat Printer Mobile Terdeteksi ({{ $connectedMobilePrinters->count() }} Terhubung)
        </h2>
        <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
          Daftar printer Bluetooth / LAN yang tersinkronisasi otomatis dari aplikasi mobile Lakasir.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <button
          type="button"
          onclick="syncMobileBridgePrinters()"
          wire:click="refreshMobilePrinters"
          class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition cursor-pointer">
          <x-heroicon-o-arrow-path class="w-4 h-4" />
          <span>Sinkron Printer Mobile</span>
        </button>

        <a
          href="/print-client.html"
          target="_blank"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 text-xs sm:text-sm font-semibold shadow-sm transition">
          <x-heroicon-o-qr-code class="w-4 h-4" />
          <span>QR &amp; Setup Mobile Bridge</span>
        </a>
      </div>
    </div>

    {{-- Grid Daftar Printer yang Sedang Konek di Mobile --}}
    @if ($connectedMobilePrinters->isNotEmpty())
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
        @foreach ($connectedMobilePrinters as $mp)
          @php
            $typeLabel = match (strtolower($mp->connection_type ?? '')) {
              'bluetooth' => 'BT',
              'lan', 'network', 'ethernet' => 'LAN',
              'usb' => 'USB',
              default => strtoupper(substr($mp->connection_type ?: 'PRN', 0, 3)),
            };
          @endphp
          <div class="flex items-start justify-between gap-3 rounded-xl border border-gray-200/80 dark:border-gray-700 bg-white dark:bg-gray-800/90 p-3.5 shadow-xs">
            <div class="flex items-start gap-3 min-w-0">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 font-bold text-xs">
                {{ $typeLabel }}
              </div>
              <div class="min-w-0">
                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">
                  {{ $mp->device_name }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                  ID: {{ $mp->device_id }} {{ $mp->address && $mp->address !== $mp->device_id ? '• ' . $mp->address : '' }}
                </p>
                <div class="mt-1 flex items-center gap-2">
                  <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                    ● Connected
                  </span>
                  <span class="text-[11px] text-gray-400">• Kertas {{ $mp->paper_width }}</span>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/40 p-4 text-xs sm:text-sm text-gray-700 dark:text-gray-300 flex items-center justify-between gap-4">
        <div>
          <p class="font-bold text-gray-900 dark:text-white">Belum Ada Perangkat Printer Mobile yang Terhubung</p>
          <p class="text-xs text-gray-500 dark:text-gray-400">Hubungkan printer Bluetooth / LAN pada aplikasi Android Lakasir, atau Anda dapat langsung menambahkan Station Printer LAN / Bluetooth / USB melalui tombol <strong>Tambah Printer</strong> di bawah.</p>
        </div>
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
          $wire.syncFromMobileBridge(parsed, 'Lakasir Android WebView');
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

      const printer = new Printer(targetId, paperWidth);
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
