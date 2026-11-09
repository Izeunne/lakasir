<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Struk #{{ $selling->code }}</title>
  <style>
    @page {
      margin: 4mm 4mm 6mm 4mm;
    }
    body {
      font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
      font-size: {{ $paperWidth === '80mm' ? '9pt' : '8pt' }};
      line-height: 1.35;
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
      border-top: 1px dashed #666;
      margin: 4px 0;
    }
    .divider-double {
      border-top: 1px solid #333;
      margin: 5px 0;
    }
    .table-full {
      width: 100%;
      border-collapse: collapse;
    }
    .table-full td {
      padding: 1.5px 0;
      vertical-align: top;
      font-size: {{ $paperWidth === '80mm' ? '9pt' : '8pt' }};
    }
    .item-name {
      font-weight: 600;
      word-break: break-word;
    }
    .sub-item {
      font-size: {{ $paperWidth === '80mm' ? '8pt' : '7pt' }};
      color: #444;
      padding-left: 6px;
    }
    .store-name {
      font-size: {{ $paperWidth === '80mm' ? '12pt' : '11pt' }};
      font-weight: bold;
      letter-spacing: 0.5px;
      margin-bottom: 2px;
    }
    .store-address {
      font-size: {{ $paperWidth === '80mm' ? '8.5pt' : '7.5pt' }};
      color: #333;
      margin-bottom: 4px;
    }
    .badge {
      display: inline-block;
      font-size: 7pt;
      padding: 1px 4px;
      border: 0.5px solid #666;
      border-radius: 3px;
      margin-top: 2px;
    }
    .footer-text {
      margin-top: 6px;
      font-size: 7pt;
      color: #555;
    }
  </style>
</head>
<body>
  <div class="text-center">
    <div class="store-name uppercase">{{ $about->shop_name ?? config('app.name', 'Lakasir POS') }}</div>
    @if(!empty($about->shop_location))
      <div class="store-address">{{ $about->shop_location }}</div>
    @endif
    @if(!empty($about->phone))
      <div class="store-address">Telp: {{ $about->phone }}</div>
    @endif
  </div>

  <div class="divider"></div>

  <table class="table-full">
    <tr>
      <td class="text-left font-bold">No: #{{ $selling->code }}</td>
      <td class="text-right">{{ \Carbon\Carbon::parse($selling->date)->setTimezone($timezone)->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
      <td class="text-left">Kasir: {{ $selling->user->cashier_name ?? $selling->user->name ?? 'Kasir' }}</td>
      <td class="text-right">
        @if(!empty($selling->order_type_label) && $selling->order_type_label !== '-')
          <span class="badge">{{ $selling->order_type_label }}</span>
        @endif
      </td>
    </tr>
    @if($selling->table)
      <tr>
        <td class="text-left" colspan="2">Meja: <strong>{{ $selling->table->number }}</strong></td>
      </tr>
    @endif
    @if($selling->member)
      <tr>
        <td class="text-left" colspan="2">Pelanggan: {{ $selling->member->name }}</td>
      </tr>
    @endif
    <tr>
      <td class="text-left" colspan="2">Pembayaran: <strong>{{ $selling->paymentMethod->name ?? 'Cash' }}</strong></td>
    </tr>
  </table>

  <div class="divider"></div>

  <table class="table-full">
    @foreach($selling->sellingDetails as $detail)
      <tr>
        <td colspan="2" class="item-name">{{ $detail->product->name ?? 'Item' }}</td>
      </tr>
      <tr>
        <td class="text-left" style="font-size: 7.5pt; color: #333;">
          {{ (float)$detail->qty }} x {{ price_format($detail->price / max(1, (float)$detail->qty)) }}
        </td>
        <td class="text-right font-bold">
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
          <td colspan="2" class="sub-item" style="font-style: italic;">
            * {{ $detail->note }}
          </td>
        </tr>
      @endif

      @if(!empty($detail->discount_price) && $detail->discount_price > 0)
        <tr>
          <td class="sub-item" style="color: #c00;">Diskon Item:</td>
          <td class="text-right" style="font-size: 7.5pt; color: #c00;">-{{ price_format($detail->discount_price) }}</td>
        </tr>
      @endif
    @endforeach
  </table>

  <div class="divider"></div>

  <table class="table-full">
    <tr>
      <td class="text-left">Subtotal</td>
      <td class="text-right">{{ price_format($selling->total_price) }}</td>
    </tr>
    @if($selling->total_discount_per_item > 0 || $selling->discount_price > 0)
      <tr>
        <td class="text-left" style="color: #c00;">Total Diskon</td>
        <td class="text-right" style="color: #c00;">-{{ price_format($selling->total_discount_per_item + $selling->discount_price) }}</td>
      </tr>
    @endif
    @if($selling->tax > 0)
      <tr>
        <td class="text-left">Pajak ({{ $selling->tax }}%)</td>
        <td class="text-right">{{ price_format($selling->tax_price) }}</td>
      </tr>
    @endif
  </table>

  <div class="divider-double"></div>

  <table class="table-full font-bold">
    <tr>
      <td class="text-left" style="font-size: 9pt;">TOTAL</td>
      <td class="text-right" style="font-size: 9.5pt;">{{ price_format($selling->grand_total_price) }}</td>
    </tr>
    <tr>
      <td class="text-left">Bayar ({{ $selling->paymentMethod->name ?? 'Cash' }})</td>
      <td class="text-right">{{ price_format($selling->payed_money) }}</td>
    </tr>
    <tr>
      <td class="text-left">Kembalian</td>
      <td class="text-right">{{ price_format($selling->money_changes) }}</td>
    </tr>
  </table>

  @if(!empty($selling->note))
    <div class="divider"></div>
    <div style="font-size: 7pt; color: #333; margin-top: 2px;">
      <strong>Catatan:</strong> {{ strip_tags($selling->note) }}
    </div>
  @endif

  <div class="divider"></div>

  <div class="text-center footer-text">
    <div>Terima kasih atas kunjungan Anda</div>
    <div style="font-size: 6.5pt; color: #888; margin-top: 3px;">Struk Digital (PDF) • Lakasir POS</div>
  </div>
</body>
</html>

