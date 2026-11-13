<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Struk #{{ $selling->code }}</title>
  <style>
    @page {
      margin: 2.5mm 2.5mm 3mm 2.5mm;
    }
    body {
      font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
      font-size: {{ $paperWidth === '80mm' ? '7.5pt' : '6.8pt' }};
      line-height: 1.22;
      color: #111;
      margin: 0;
      padding: 0;
    }
    .text-center { text-align: center; }
    .text-left { text-align: left; }
    .text-right { text-align: right; }
    .font-bold { font-weight: bold; }
    .uppercase { text-transform: uppercase; }
    
    .divider {
      border-top: 0.75px dashed #888;
      margin: 3px 0;
    }
    .divider-double {
      border-top: 1.2px solid #222;
      margin: 3.5px 0;
    }
    .table-full {
      width: 100%;
      border-collapse: collapse;
    }
    .table-full td {
      padding: 0.75px 0;
      vertical-align: top;
      font-size: {{ $paperWidth === '80mm' ? '7.5pt' : '6.8pt' }};
    }
    
    /* Hierarchy: Header */
    .store-name {
      font-size: {{ $paperWidth === '80mm' ? '8.8pt' : '7.8pt' }};
      font-weight: bold;
      letter-spacing: 0.3px;
      line-height: 1.15;
      margin-bottom: 1px;
    }
    .store-meta {
      font-size: {{ $paperWidth === '80mm' ? '6.5pt' : '5.8pt' }};
      color: #555;
      line-height: 1.15;
    }
    
    /* Hierarchy: Transaction Meta */
    .meta-table td {
      font-size: {{ $paperWidth === '80mm' ? '7pt' : '6.2pt' }};
      padding: 0.5px 0;
    }
    .badge {
      display: inline-block;
      font-size: {{ $paperWidth === '80mm' ? '6.5pt' : '5.8pt' }};
      font-weight: bold;
      padding: 0 3px;
      border: 0.5px solid #444;
      border-radius: 2px;
    }
    .table-number-box {
      font-weight: bold;
      font-size: {{ $paperWidth === '80mm' ? '7.8pt' : '7pt' }};
      color: #000;
    }
    
    /* Hierarchy: Items */
    .item-name {
      font-size: {{ $paperWidth === '80mm' ? '7.8pt' : '7.1pt' }};
      font-weight: bold;
      color: #000;
      word-break: break-word;
      padding-top: 1.5px;
    }
    .item-calc {
      font-size: {{ $paperWidth === '80mm' ? '6.8pt' : '6pt' }};
      color: #555;
    }
    .item-total {
      font-size: {{ $paperWidth === '80mm' ? '7.8pt' : '7.1pt' }};
      font-weight: bold;
      color: #000;
    }
    .sub-item {
      font-size: {{ $paperWidth === '80mm' ? '6.5pt' : '5.8pt' }};
      color: #666;
      padding-left: 4px;
    }
    .item-note {
      font-size: {{ $paperWidth === '80mm' ? '6.2pt' : '5.6pt' }};
      color: #555;
      font-style: italic;
      padding-left: 4px;
    }
    
    /* Hierarchy: Totals & Grand Total */
    .summary-table td {
      padding: 0.5px 0;
      font-size: {{ $paperWidth === '80mm' ? '7.2pt' : '6.5pt' }};
      color: #444;
    }
    .grand-total-box {
      border-top: 1.2px solid #111;
      border-bottom: 1.2px solid #111;
      padding: 2px 0;
      margin: 2.5px 0;
    }
    .grand-total-label {
      font-size: {{ $paperWidth === '80mm' ? '10.5pt' : '9.5pt' }};
      font-weight: 900;
      color: #000;
    }
    .grand-total-value {
      font-size: {{ $paperWidth === '80mm' ? '10.5pt' : '9.5pt' }};
      font-weight: 900;
      color: #000;
    }
    .payment-row td {
      font-size: {{ $paperWidth === '80mm' ? '7.5pt' : '6.8pt' }};
      padding: 0.5px 0;
    }
    
    /* Hierarchy: Footer */
    .footer-text {
      margin-top: 4px;
      font-size: {{ $paperWidth === '80mm' ? '6.2pt' : '5.6pt' }};
      color: #666;
      line-height: 1.2;
    }
  </style>
</head>
<body>
  @php
    $logoBase64 = null;
    if (!empty($about?->photo)) {
        $uploadDisk = config('filesystems.upload_disk', 'public');
        try {
            if (\Illuminate\Support\Facades\Storage::disk($uploadDisk)->exists($about->photo)) {
                $path = \Illuminate\Support\Facades\Storage::disk($uploadDisk)->path($about->photo);
                if (file_exists($path)) {
                    $mime = @mime_content_type($path) ?: 'image/png';
                    $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        } catch (\Throwable $e) {
            // Silently fallback
        }
    }

    $contactParts = array_filter([
        !empty($about?->phone) ? 'Telp: ' . $about->phone : null,
        !empty($about?->npwp) ? 'NPWP: ' . $about->npwp : null,
    ]);

    $webParts = array_filter([
        !empty($about?->website) ? $about->website : null,
        !empty($about?->email) ? $about->email : null,
    ]);
  @endphp

  <!-- Header Section with Logo Centered on Top -->
  <div class="text-center">
    @if($logoBase64)
      <div style="margin-bottom: 3px;">
        <img src="{{ $logoBase64 }}" style="max-height: 40px; max-width: 120px; object-fit: contain;">
      </div>
    @endif
    <div class="store-name uppercase">{{ $about->shop_name ?? config('app.name', 'Lakasir POS') }}</div>
    
    @if(!empty($about->shop_location))
      <div class="store-meta">{{ $about->shop_location }}</div>
    @endif
    
    @if(!empty($contactParts))
      <div class="store-meta">{{ implode(' • ', $contactParts) }}</div>
    @endif

    @if(!empty($webParts))
      <div class="store-meta">{{ implode(' • ', $webParts) }}</div>
    @endif
  </div>

  <div class="divider"></div>

  <!-- Meta Info Transaksi (Compact) -->
  <table class="table-full meta-table">
    <tr>
      <td class="text-left font-bold">#{{ $selling->code }}</td>
      <td class="text-right">{{ \Carbon\Carbon::parse($selling->date)->setTimezone($timezone)->format('d/m/y H:i') }}</td>
    </tr>
    <tr>
      <td class="text-left">Kasir: {{ $selling->user->cashier_name ?? $selling->user->name ?? 'Kasir' }}</td>
      <td class="text-right">
        @if(!empty($selling->order_type_label) && $selling->order_type_label !== '-')
          <span class="badge">{{ $selling->order_type_label }}</span>
        @endif
      </td>
    </tr>
    @if($selling->table || $selling->member)
      <tr>
        <td class="text-left" colspan="2">
          @if($selling->table)
            <span class="table-number-box">MEJA: {{ $selling->table->number }}</span>
          @endif
          @if($selling->table && $selling->member)
            <span style="color: #888;"> • </span>
          @endif
          @if($selling->member)
            <span style="color: #444;">{{ $selling->member->name }}</span>
          @endif
        </td>
      </tr>
    @endif
  </table>

  <div class="divider"></div>

  <!-- Item Details -->
  <table class="table-full">
    @foreach($selling->sellingDetails as $detail)
      <tr>
        <td colspan="2" class="item-name">{{ $detail->product->name ?? 'Item' }}</td>
      </tr>
      <tr>
        <td class="text-left item-calc">
          {{ (float)$detail->qty }} x {{ price_format($detail->price / max(1, (float)$detail->qty)) }}
        </td>
        <td class="text-right item-total">
          {{ price_format($detail->price) }}
        </td>
      </tr>

      @if(!empty($detail->selected_variations))
        @foreach($detail->selected_variations as $var)
          <tr>
            <td colspan="2" class="sub-item">
              + {{ $var['variation_name'] ?? 'Variasi' }}: {{ $var['option_name'] ?? '' }}
              @if(!empty($var['price']) && $var['price'] > 0)
                (+{{ price_format($var['price']) }})
              @endif
            </td>
          </tr>
        @endforeach
      @endif

      @if(!empty($detail->note))
        <tr>
          <td colspan="2" class="item-note">
            * {{ $detail->note }}
          </td>
        </tr>
      @endif

      @if(!empty($detail->discount_price) && $detail->discount_price > 0)
        <tr>
          <td class="sub-item" style="color: #b00;">Diskon:</td>
          <td class="text-right item-calc" style="color: #b00;">-{{ price_format($detail->discount_price) }}</td>
        </tr>
      @endif
    @endforeach
  </table>

  <div class="divider"></div>

  <!-- Summary Subtotal & Diskon -->
  <table class="table-full summary-table">
    <tr>
      <td class="text-left">Subtotal</td>
      <td class="text-right">{{ price_format($selling->total_price) }}</td>
    </tr>
    @if($selling->total_discount_per_item > 0 || $selling->discount_price > 0)
      <tr>
        <td class="text-left" style="color: #b00;">Diskon</td>
        <td class="text-right" style="color: #b00;">-{{ price_format($selling->total_discount_per_item + $selling->discount_price) }}</td>
      </tr>
    @endif
    @if($selling->tax > 0)
      <tr>
        <td class="text-left">Pajak ({{ $selling->tax }}%)</td>
        <td class="text-right">{{ price_format($selling->tax_price) }}</td>
      </tr>
    @endif
  </table>

  <!-- TOTAL HIGHLIGHT (SANGAT MENONJOL) -->
  <div class="grand-total-box">
    <table class="table-full">
      <tr>
        <td class="text-left grand-total-label">TOTAL</td>
        <td class="text-right grand-total-value">{{ price_format($selling->grand_total_price) }}</td>
      </tr>
    </table>
  </div>

  <!-- Pembayaran & Kembalian -->
  <table class="table-full payment-row">
    <tr>
      <td class="text-left" style="color: #444;">Bayar ({{ $selling->paymentMethod->name ?? 'Cash' }})</td>
      <td class="text-right font-bold">{{ price_format($selling->payed_money) }}</td>
    </tr>
    <tr>
      <td class="text-left font-bold">Kembalian</td>
      <td class="text-right font-bold">{{ price_format($selling->money_changes) }}</td>
    </tr>
  </table>

  @if(!empty($selling->note))
    <div class="divider"></div>
    <div style="font-size: 5.8pt; color: #444;">
      <strong>Note:</strong> {{ strip_tags($selling->note) }}
    </div>
  @endif

  <div class="divider"></div>

  <!-- Footer (Small & Compact) -->
  <div class="text-center footer-text">
    <div style="font-weight: 600;">Terima kasih atas kunjungan Anda</div>
  </div>
</body>
</html>
