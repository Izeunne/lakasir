<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preview Desain Nota - #{{ $selling->code }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;500;600;700;800&display=swap');
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #0b0f19;
      color: #334155;
    }
    .thermal-paper {
      font-family: 'Courier Prime', Courier, monospace;
      background: #ffffff;
      color: #111827;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
      position: relative;
    }
    .thermal-paper::after {
      content: "";
      position: absolute;
      bottom: -8px;
      left: 0;
      right: 0;
      height: 8px;
      background: radial-gradient(circle, transparent, transparent 50%, #ffffff 50%, #ffffff 100% );
      background-size: 14px 14px;
    }
    .dashed-line {
      border-top: 1px dashed #6b7280;
      margin: 6px 0;
    }
    .double-line {
      border-top: 2px dashed #111827;
      margin: 6px 0;
    }
    .solid-divider {
      border-top: 1.5px solid #111827;
      border-bottom: 1.5px solid #111827;
      padding: 3px 0;
      margin: 4px 0;
    }
    @media print {
      body {
        background-color: white !important;
        color: black !important;
      }
      .no-print {
        display: none !important;
      }
      .preview-grid {
        display: block !important;
      }
      .thermal-paper {
        box-shadow: none !important;
        page-break-after: always;
        margin-bottom: 15mm;
      }
      .thermal-paper::after {
        display: none !important;
      }
    }
  </style>
</head>
<body class="min-h-screen py-6 px-4 sm:px-6 lg:px-8">

  @php
    $foodCategories = ['Makanan Utama', 'Camilan & Penutup', 'Makanan'];
    $drinkCategories = ['Minuman'];

    $foodDetails = $selling->sellingDetails->filter(function($detail) use ($foodCategories) {
        $cat = $detail->product?->category?->name ?? '';
        return in_array($cat, $foodCategories, true) || empty($cat);
    });

    $drinkDetails = $selling->sellingDetails->filter(function($detail) use ($drinkCategories) {
        $cat = $detail->product?->category?->name ?? '';
        return in_array($cat, $drinkCategories, true);
    });

    $totalFoodQty = $foodDetails->sum('qty');
    $totalDrinkQty = $drinkDetails->sum('qty');

    $contactParts = array_filter([
        !empty($about?->phone) ? 'Telp: ' . $about->phone : null,
        !empty($about?->npwp) ? 'NPWP: ' . $about->npwp : null,
    ]);

    $webParts = array_filter([
        !empty($about?->website) ? $about->website : null,
        !empty($about?->email) ? $about->email : null,
    ]);
  @endphp

  <!-- Header Control Bar -->
  <div class="max-w-7xl mx-auto mb-6 no-print">
    <div class="bg-slate-900/90 backdrop-blur rounded-2xl p-5 border border-slate-800 shadow-2xl">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
              ● Desain Ringkas & Hierarki Visual Baru
            </span>
            <span class="text-xs text-slate-400">#{{ $selling->code }}</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-bold text-white mt-1.5">
            Preview Desain Semua Nota (Ringkas & Proporsional)
          </h1>
          <p class="text-xs text-slate-300 mt-0.5">
            Kop dibuat padat & hemat ruang • Meja & Item di-highlight • <span class="text-amber-300 font-semibold">TOTAL berukuran paling dominan</span> • Tanpa harga di Dapur & Bar.
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap items-center gap-2">
          <a href="{{ route('selling.receipt.pdf', ['selling' => $selling, 'width' => '58mm']) }}" target="_blank"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-600 hover:bg-blue-500 text-white transition shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Buka PDF 58mm
          </a>
          <a href="{{ route('selling.receipt.pdf', ['selling' => $selling, 'width' => '80mm']) }}" target="_blank"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white transition shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Buka PDF 80mm
          </a>
          <button onclick="window.print()"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-700 hover:bg-slate-600 text-slate-100 transition shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print
          </button>
          <a href="/tenant/sellings/{{ $selling->id }}"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-sm">
            ← Kembali
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Thermal Paper Grid Preview -->
  <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 items-start preview-grid">

    <!-- ========================================== -->
    <!-- NOTA 1: STRUK PELANGGAN (KASIR RANGKAP 1) -->
    <!-- ========================================== -->
    <div class="flex flex-col">
      <div class="mb-1.5 text-center no-print">
        <span class="inline-block bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[11px] px-2 py-0.5 rounded-full font-semibold">
          1. Struk Pelanggan (Kasir)
        </span>
      </div>

      <div class="thermal-paper rounded-t-lg p-3 text-[10.5px] leading-snug">
        <!-- Header Ringkas (Logo di Atas Tengah & Nama Lebih Kecil) -->
        <div class="text-center mb-1">
          @if($about && !empty($about->photo))
            <div class="flex justify-center mb-1.5">
              <img src="{{ $about->logo_url }}" alt="Logo" class="h-10 object-contain max-w-[120px]" onerror="this.style.display='none'">
            </div>
          @endif
          <div class="font-bold text-[10.5px] uppercase tracking-wide text-gray-950">{{ $about->shop_name ?? 'WAROENG BERIBU RASA RENON' }}</div>
          @if(!empty($about->shop_location))
            <div class="text-[9px] text-gray-600 leading-tight mt-0.5">{{ $about->shop_location }}</div>
          @endif
          @if(!empty($contactParts))
            <div class="text-[8.5px] text-gray-500 leading-tight">{{ implode(' • ', $contactParts) }}</div>
          @endif
          @if(!empty($webParts))
            <div class="text-[8.5px] text-gray-500 leading-tight">{{ implode(' • ', $webParts) }}</div>
          @endif
        </div>

        <div class="dashed-line"></div>

        <!-- Meta Transaksi Rapat -->
        <div class="space-y-0.5 text-[9.5px]">
          <div class="flex justify-between font-bold text-gray-900">
            <span>#{{ $selling->code }}</span>
            <span class="font-normal text-gray-600">{{ now()->parse($selling->date)->format('d/m/y H:i') }}</span>
          </div>
          <div class="flex justify-between text-gray-600">
            <span>Kasir: {{ $selling->user->name ?? 'Kasir' }}</span>
            <span class="border border-gray-400 px-1 rounded text-[8.5px] font-bold text-gray-800 uppercase">Dine In</span>
          </div>
          <div class="flex justify-between items-center pt-0.5">
            <span class="font-bold text-[10.5px] text-gray-950">MEJA: {{ $selling->table?->number ?? '05' }}</span>
            <span class="text-gray-600 text-[9px]">{{ $selling->member?->name ?? 'Tamu' }}</span>
          </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Items Table dengan Hierarki Jelas -->
        <div class="space-y-1.5">
          @foreach($selling->sellingDetails as $detail)
            <div>
              <div class="font-bold text-gray-950 text-[10.5px]">{{ $detail->product->name }}</div>
              <div class="flex justify-between text-[9.5px] text-gray-600">
                <span>{{ number_format($detail->qty, 0) }} x {{ number_format($detail->price, 0, ',', '.') }}</span>
                <span class="font-bold text-gray-950">{{ number_format($detail->total_price ?? ($detail->qty * $detail->price), 0, ',', '.') }}</span>
              </div>
              @if(!empty($detail->note))
                <div class="text-[8.5px] text-gray-500 italic pl-1.5">↳ {{ $detail->note }}</div>
              @endif
            </div>
          @endforeach
        </div>

        <div class="dashed-line"></div>

        <!-- Subtotal Ringkas -->
        <div class="space-y-0.5 text-[9.5px] text-gray-600">
          <div class="flex justify-between">
            <span>Subtotal</span>
            <span>Rp {{ number_format($selling->total_price, 0, ',', '.') }}</span>
          </div>
          @if(!empty($selling->discount_price) && $selling->discount_price > 0)
            <div class="flex justify-between text-red-600">
              <span>Diskon</span>
              <span>-Rp {{ number_format($selling->discount_price, 0, ',', '.') }}</span>
            </div>
          @endif
        </div>

        <!-- TOTAL (PALING BESAR & DOMINAN) -->
        <div class="solid-divider">
          <div class="flex justify-between items-center">
            <span class="font-black text-[13px] text-gray-950 tracking-tight">TOTAL</span>
            <span class="font-black text-[14px] text-gray-950">Rp {{ number_format($selling->grand_total_price ?? $selling->total_price, 0, ',', '.') }}</span>
          </div>
        </div>

        <!-- Pembayaran & Kembalian -->
        <div class="space-y-0.5 text-[9.5px]">
          <div class="flex justify-between text-gray-700">
            <span>Bayar ({{ $selling->paymentMethod->name ?? 'Tunai' }})</span>
            <span class="font-bold text-gray-900">Rp {{ number_format($selling->payed_money ?: 150000, 0, ',', '.') }}</span>
          </div>
          <div class="flex justify-between font-bold text-gray-950 text-[10px]">
            <span>Kembalian</span>
            <span>Rp {{ number_format($selling->money_changes ?: 17000, 0, ',', '.') }}</span>
          </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Footer Kecil -->
        <div class="text-center text-[8.5px] text-gray-500 my-1">
          <p class="font-semibold text-gray-700">Terima kasih atas kunjungan Anda</p>
        </div>
      </div>
    </div>


    <!-- ========================================== -->
    <!-- NOTA 2: SALINAN TOKO / ARSIP KASIR         -->
    <!-- ========================================== -->
    <div class="flex flex-col">
      <div class="mb-1.5 text-center no-print">
        <span class="inline-block bg-blue-500/20 text-blue-300 border border-blue-500/30 text-[11px] px-2 py-0.5 rounded-full font-semibold">
          2. Salinan Toko (Arsip Kasir)
        </span>
      </div>

      <div class="thermal-paper rounded-t-lg p-3 text-[10.5px] leading-snug">
        <!-- Label Salinan Toko -->
        <div class="text-center mb-1">
          <div class="font-extrabold text-[11px] tracking-wide text-gray-950 border border-gray-900 py-0.5 px-1 uppercase">
            *** SALINAN TOKO ***
          </div>
          <div class="text-[8px] text-gray-500 mt-0.5">UNTUK ARSIP KASIR / PEMBUKUAN</div>
        </div>

        <div class="dashed-line"></div>

        <div class="space-y-0.5 text-[9.5px]">
          <div class="flex justify-between font-bold text-gray-900">
            <span>#{{ $selling->code }}</span>
            <span class="font-normal text-gray-600">{{ now()->parse($selling->date)->format('d/m/y H:i') }}</span>
          </div>
          <div class="flex justify-between">
            <span class="font-bold text-gray-950">MEJA: {{ $selling->table?->number ?? '05' }}</span>
            <span class="text-gray-600 text-[9px]">Kasir: {{ $selling->user->name ?? 'Kasir' }}</span>
          </div>
        </div>

        <div class="dashed-line"></div>

        <div class="space-y-1 text-[9.5px]">
          @foreach($selling->sellingDetails as $detail)
            <div class="flex justify-between">
              <span class="truncate max-w-[150px] font-medium">{{ number_format($detail->qty, 0) }}x {{ $detail->product->name }}</span>
              <span class="font-bold font-mono">{{ number_format($detail->total_price ?? ($detail->qty * $detail->price), 0, ',', '.') }}</span>
            </div>
          @endforeach
        </div>

        <!-- TOTAL HIGHLIGHT -->
        <div class="solid-divider">
          <div class="flex justify-between items-center">
            <span class="font-black text-[12px] text-gray-950">TOTAL</span>
            <span class="font-black text-[13px] text-gray-950">Rp {{ number_format($selling->grand_total_price ?? $selling->total_price, 0, ',', '.') }}</span>
          </div>
        </div>

        <div class="space-y-0.5 text-[9px] text-gray-700">
          <div class="flex justify-between">
            <span>Bayar ({{ $selling->paymentMethod->name ?? 'Tunai' }})</span>
            <span>{{ number_format($selling->payed_money ?: 150000, 0, ',', '.') }}</span>
          </div>
          <div class="flex justify-between font-bold text-gray-950">
            <span>Kembalian</span>
            <span>{{ number_format($selling->money_changes ?: 17000, 0, ',', '.') }}</span>
          </div>
        </div>

        <div class="dashed-line"></div>

        <div class="mt-3 text-center text-[8.5px] text-gray-600">
          <p>Paraf Kasir:</p>
          <div class="h-6 border-b border-gray-400 w-2/3 mx-auto mt-1"></div>
        </div>
      </div>
    </div>


    <!-- ========================================== -->
    <!-- NOTA 3: CHECKER DAPUR (MAKANAN)            -->
    <!-- ========================================== -->
    <div class="flex flex-col">
      <div class="mb-1.5 text-center no-print">
        <span class="inline-block bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[11px] px-2 py-0.5 rounded-full font-semibold">
          3. Checker Dapur (KOT)
        </span>
      </div>

      <div class="thermal-paper rounded-t-lg p-3 text-[11px] leading-snug">
        <div class="text-center mb-1">
          <div class="font-extrabold text-[12px] uppercase text-gray-950">
            *** PESANAN DAPUR ***
          </div>
          <div class="text-[8.5px] font-semibold text-gray-500 uppercase">
            STATION: DAPUR (MAKANAN)
          </div>
        </div>

        <!-- Highlight Nomor Meja -->
        <div class="border border-gray-900 rounded p-1.5 text-center my-1.5">
          <div class="text-[9px] uppercase font-bold text-gray-700">NOMOR MEJA</div>
          <div class="text-2xl font-black text-gray-950 tracking-wider">
            MEJA {{ $selling->table?->number ?? '05' }}
          </div>
          <div class="text-[8.5px] text-gray-600 font-medium">
            #{{ $selling->code }} • {{ now()->parse($selling->date)->format('H:i') }}
          </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Daftar Menu Makanan Saja (Tanpa Harga, Tanpa Badge Warna, Tanpa Emot) -->
        <div class="space-y-2 my-1">
          @forelse($foodDetails as $detail)
            <div class="border-b border-dashed border-gray-300 pb-1.5">
              <div class="flex items-start gap-2">
                <span class="font-black text-[12px] text-gray-950 min-w-[24px]">
                  {{ number_format($detail->qty, 0) }}x
                </span>
                <div class="flex-1">
                  <div class="font-extrabold text-[11.5px] text-gray-950 leading-tight">{{ $detail->product->name }}</div>
                  @if(!empty($detail->note))
                    <div class="text-[9.5px] font-medium text-gray-700 mt-0.5 italic">
                      * Catatan: {{ $detail->note }}
                    </div>
                  @endif
                </div>
              </div>
            </div>
          @empty
            <div class="text-center text-gray-500 py-2 italic text-[10px]">Tidak ada item makanan</div>
          @endforelse
        </div>

        <div class="solid-divider">
          <div class="flex justify-between items-center text-[10.5px] font-extrabold text-gray-950">
            <span>TOTAL MAKANAN</span>
            <span class="text-[12px]">{{ $totalFoodQty }} Porsi</span>
          </div>
        </div>

        <div class="text-center text-[8px] text-gray-400 mt-1">
          (Hanya Instruksi Memasak • Non-Harga)
        </div>
      </div>
    </div>


    <!-- ========================================== -->
    <!-- NOTA 4: CHECKER BAR (MINUMAN)              -->
    <!-- ========================================== -->
    <div class="flex flex-col">
      <div class="mb-1.5 text-center no-print">
        <span class="inline-block bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[11px] px-2 py-0.5 rounded-full font-semibold">
          4. Checker Bar (BOT)
        </span>
      </div>

      <div class="thermal-paper rounded-t-lg p-3 text-[11px] leading-snug">
        <div class="text-center mb-1">
          <div class="font-extrabold text-[12px] uppercase text-gray-950">
            *** PESANAN BAR ***
          </div>
          <div class="text-[8.5px] font-semibold text-gray-500 uppercase">
            STATION: BAR (MINUMAN)
          </div>
        </div>

        <!-- Highlight Meja -->
        <div class="border border-gray-900 rounded p-1.5 text-center my-1.5">
          <div class="text-[9px] uppercase font-bold text-gray-700">NOMOR MEJA</div>
          <div class="text-2xl font-black text-gray-950 tracking-wider">
            MEJA {{ $selling->table?->number ?? '05' }}
          </div>
          <div class="text-[8.5px] text-gray-600 font-medium">
            #{{ $selling->code }} • {{ now()->parse($selling->date)->format('H:i') }}
          </div>
        </div>

        <div class="dashed-line"></div>

        <!-- Daftar Minuman Saja (Tanpa Harga, Tanpa Badge Warna, Tanpa Emot) -->
        <div class="space-y-2 my-1">
          @forelse($drinkDetails as $detail)
            <div class="border-b border-dashed border-gray-300 pb-1.5">
              <div class="flex items-start gap-2">
                <span class="font-black text-[12px] text-gray-950 min-w-[24px]">
                  {{ number_format($detail->qty, 0) }}x
                </span>
                <div class="flex-1">
                  <div class="font-extrabold text-[11.5px] text-gray-950 leading-tight">{{ $detail->product->name }}</div>
                  @if(!empty($detail->note))
                    <div class="text-[9.5px] font-medium text-gray-700 mt-0.5 italic">
                      * Catatan: {{ $detail->note }}
                    </div>
                  @endif
                </div>
              </div>
            </div>
          @empty
            <div class="text-center text-gray-500 py-2 italic text-[10px]">Tidak ada item minuman</div>
          @endforelse
        </div>

        <div class="solid-divider">
          <div class="flex justify-between items-center text-[10.5px] font-extrabold text-gray-950">
            <span>TOTAL MINUMAN</span>
            <span class="text-[12px]">{{ $totalDrinkQty }} Gelas</span>
          </div>
        </div>

        <div class="text-center text-[8px] text-gray-400 mt-1">
          (Hanya Racikan Barista • Non-Harga)
        </div>
      </div>
    </div>

  </div>

</body>
</html>
