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
    <h1 class="text-3xl font-semibold">{{ __('Product Report') }}</h1>
    <h3 class="text-xl">{{ $header['shop_name'] }}</h3>
  </div>
  <p class="mb-4">{{ __('Period') }}: <b>{{ $header['start_date'] }} - {{ $header['end_date'] }}</b></p>
  <x-table class="w-full table-fixed">
    <x-table-header>
      <x-table-header-cell style="width: 13%;">SKU</x-table-header-cell>
      <x-table-header-cell class="product-name-col" style="width: 23%;">{{ __('Product Name') }}</x-table-header-cell>
      <x-table-header-cell style="width: 10%; text-align: right;" class="number">{{ __('Price') }}</x-table-header-cell>
      <x-table-header-cell style="width: 6%; text-align: right;" class="number">Qty</x-table-header-cell>
      <x-table-header-cell style="width: 10%; text-align: right;" class="number">{{ __('Selling') }}</x-table-header-cell>
      <x-table-header-cell style="width: 7%; text-align: right;" class="number">Dsc</x-table-header-cell>
      <x-table-header-cell style="width: 11%; text-align: right;" class="number">{{ __('Net Selling') }}</x-table-header-cell>
      <x-table-header-cell style="width: 10%; text-align: right;" class="number">{{ __('Gross Profit') }}</x-table-header-cell>
      <x-table-header-cell style="width: 10%; text-align: right;" class="number">{{ __('Net Profit') }}</x-table-header-cell>
    </x-table-header>
    <tbody>
      @foreach($reports as $key => $report)
        <x-table-row>
          <!-- <td>{{ $report['code'] }}</td> -->
          <x-table-cell>{{ $report['sku'] }}</x-table-cell>
          <x-table-cell class="product-name-col" style="white-space: normal;">{{ $report['name'] }}</x-table-cell>
          <!-- <td class="number">{{ $report['initial_price'] }}</td> -->
          <x-table-cell class="number" style="text-align: right;">{{ $report['selling_price'] }}</x-table-cell>
          <x-table-cell class="number" style="text-align: right;">{{ $report['qty'] }}</x-table-cell>
          <x-table-cell class="number" style="text-align: right;">{{ $report['selling'] }}</x-table-cell>
          <x-table-cell class="number" style="text-align: right;">{{ $report['discount_price'] }}</x-table-cell>
          <!-- <td class="number">{{ $report['cost'] }}</td> -->
          <x-table-cell class="number" style="text-align: right;">{{ $report['total_after_discount'] }}</x-table-cell>
          <x-table-cell class="number" style="text-align: right;">{{ $report['gross_profit'] }}</x-table-cell>
          <x-table-cell class="number" style="text-align: right;">{{ $report['net_profit'] }}</x-table-cell>
        </x-table-row>
      @endforeach
      <x-table-row>
        <x-table-cell colspan="3"><b>{{ __('Total') }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_qty'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_gross'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_all_discount_per_item'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_gross_profit'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net_profit_before_discount_selling'] }}</b></x-table-cell>
      </x-table-row>
    </tbody>
  </x-table>

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
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_all_discount_per_item'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_gross_profit'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net_profit_before_discount_selling'] }}</b></x-table-cell>
        <x-table-cell class="number" style="text-align: right;"><b>{{ $footer['total_net_profit_after_discount_selling'] }}</b></x-table-cell>
      </x-table-row>
    </tbody>
  </x-table>

</div>
