<div class="max-w-full report-table-container">
  <style>
    .report-table-container th,
    .report-table-container td {
      padding: 8px 10px !important;
      vertical-align: top;
    }
    .report-table-container th.number,
    .report-table-container td.number {
      text-align: right !important;
    }
    .report-table-container td.product-name-col,
    .report-table-container th.product-name-col {
      white-space: normal !important;
      word-break: break-word;
    }
  </style>
  <div class="text-center space-y-2">
    <h1 class="text-3xl font-semibold">Laporan kasir</h1>
    <h3 class="text-xl">{{ $header['shop_name'] }}</h3>
  </div>
  <p class="mb-4">{{ __('Period') }}: <b>{{ $header['start_date'] }} - {{ $header['end_date'] }}</b></p>
  <div class="space-y-4">
    @foreach($reports as $key => $report)
      <x-table class="w-full table-fixed">
        <x-table-header>
          <x-table-row>
            <x-table-header-cell colspan="3">{{ __('Cashier') }} : {{ $report['user'] }}</x-table-header-cell>
            <x-table-header-cell colspan="2"># {{ $report['number'] }}</x-table-header-cell>
            <x-table-header-cell colspan="2" class="number" style="text-align: right;">{{ __('Date') }} : {{ $report['created_at'] }}</x-table-header-cell>
          </x-table-row>
          <x-table-row>
            <x-table-header-cell class="product-name-col" style="width: 26%;">{{ __('Product Name') }}</x-table-header-cell>
            <x-table-header-cell style="width: 14%; text-align: right;" class="number">{{ __('Price') }} x Qty</x-table-header-cell>
            <x-table-header-cell style="width: 14%; text-align: right;" class="number">{{ __('Cost') }} x Qty</x-table-header-cell>
            <x-table-header-cell style="width: 8%; text-align: right;" class="number">Dsc</x-table-header-cell>
            <x-table-header-cell style="width: 13%; text-align: right;" class="number">{{ __('Total Harga') }}</x-table-header-cell>
            <x-table-header-cell style="width: 12%; text-align: right;" class="number">{{ __('Total Cost') }}</x-table-header-cell>
            <x-table-header-cell style="width: 13%; text-align: right;" class="number">{{ __('Total Sesudah Dsc') }}</x-table-header-cell>
            <!-- <th style="width: 100px;" class="number">{{ __('Keuntungan Kotor') }}</th>
              <th style="width: 100px;" class="number">{{ __('Keuntungan Bersih') }}</th> -->
          </x-table-row>
        </x-table-header>
        <tbody>
          @foreach($report['transaction']['items'] as $item)
            <x-table-row>
              <x-table-cell class="product-name-col" style="white-space: normal;">{{ $item['product'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $item['product_price'] }} x {{ $item['quantity'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $item['product_cost'] }} x {{ $item['quantity'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">({{ $item['discount_price'] }})</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $item['price'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $item['cost'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $item['total_after_discount'] }}</x-table-cell>
              <!-- <td class="number">{{ $item['gross_profit'] }}</td>
                <td class="number">{{ $item['net_profit'] }}</td> -->
            </x-table-row>
          @endforeach
          <x-table-row>
            <x-table-cell colspan="3"><b>{{ __('Sub Total') }}</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>({{ $report['total']['discount'] }})</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>{{ $report['total']['gross_selling'] }}</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>{{ $report['total']['cost'] }}</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>{{ $report['total']['net_selling'] }}</b></x-table-cell>
          </x-table-row>
          <x-table-row>
            <x-table-cell colspan="6"><b>{{ __('Dsc Penjualan') }}</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>({{ $report['total']['discount_selling'] }})</b></x-table-cell>
          </x-table-row>
          <x-table-row>
            <x-table-cell colspan="6"><b>Total</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>{{ $report['total']['grand_total'] }}</b></x-table-cell>
          </x-table-row>
        </tbody>
      </x-table>
    @endforeach
  </div>
  <x-table class="w-full table-fixed mt-4">
    <x-table-header>
      <x-table-row>
        <x-table-header-cell colspan="8" style="text-align: center;">{{ __('Grand Total') }}</x-table-header-cell>
      </x-table-row>
      <x-table-row>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Cost') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Penjualan') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Dsc per Penjualan') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Dsc per Item') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Penjualan Setelah Dsc') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Keuntungan Kotor') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Keuntungan Bersih Sebelum Diskon Penjualan') }}</x-table-header-cell>
        <x-table-header-cell class="number" style="text-align: right; white-space: normal;">{{ __('Keuntungan Bersih Setelah Diskon Penjualan') }}</x-table-header-cell>
      </x-table-row>
    </x-table-header>
    <tbody>
      <x-table-row>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_cost'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_gross'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_discount'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_discount_per_item'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_gross_profit'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net_profit_before_discount_selling'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net_profit_after_discount_selling'] }}</b></x-table-cell>
      </x-table-row>
    </tbody>
  </x-table>

</div>
