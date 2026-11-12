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
    <h1 class="text-3xl font-semibold">{{ __('Purchasing Report') }}</h1>
    <h3 class="text-xl">{{ $header['shop_name'] }}</h3>
  </div>
  <p class="mb-4">{{ __('Period') }}: <b>{{ $header['start_date'] }} - {{ $header['end_date'] }}</b></p>
  <div class="space-y-4">
    @foreach($reports as $key => $report)
      <x-table class="w-full table-fixed">
        <x-table-header>
          <x-table-row>
            <x-table-header-cell colspan="6"> {{ __('Supplier') }}:</x-table-header-cell>
            <x-table-header-cell class="number" style="text-align: right;"> {{ $report['supplier'] }}</x-table-header-cell>
          </x-table-row>
          <x-table-row>
            <x-table-header-cell colspan="6"> {{ __('Date') }}:</x-table-header-cell>
            <x-table-header-cell class="number" style="text-align: right;"> {{ $report['date'] }}</x-table-header-cell>
          </x-table-row>
        </x-table-header>
        <x-table-header>
          <x-table-header-cell class="product-name-col" style="width: 26%;">{{ __('Product name') }}</x-table-header-cell>
          <x-table-header-cell style="width: 10%;">{{ __('Unit') }}</x-table-header-cell>
          <x-table-header-cell class="number" style="width: 8%; text-align: right;">Qty</x-table-header-cell>
          <x-table-header-cell class="number" style="width: 14%; text-align: right;">{{ __('Cost per stock') }}</x-table-header-cell>
          <x-table-header-cell class="number" style="width: 14%; text-align: right;">{{ __('Total cost') }}</x-table-header-cell>
          <x-table-header-cell class="number" style="width: 14%; text-align: right;">{{ __('Price per stock') }}</x-table-header-cell>
          <x-table-header-cell class="number" style="width: 14%; text-align: right;">{{ __('Total price') }}</x-table-header-cell>
        </x-table-header>
        <tbody>
          @foreach ($report['stocks'] as $key => $stock)
            <x-table-row>
              <x-table-cell class="product-name-col" style="white-space: normal;">{{ $stock['product_name'] }}</x-table-cell>
              <x-table-cell>{{ $stock['product_unit'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $stock['init_stock'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $stock['initial_price'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $stock['total_initial_price'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $stock['selling_price'] }}</x-table-cell>
              <x-table-cell class="number" style="text-align: right;">{{ $stock['total_selling_price'] }}</x-table-cell>
            </x-table-row>
          @endforeach
          <x-table-row class="border-t border-gray-200 dark:border-gray-600">
            <x-table-cell colspan="4"><b>{{ __('Subtotal') }}</b></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>{{ $report['subtotal_total_initial_price'] }}</b></x-table-cell>
            <x-table-cell></x-table-cell>
            <x-table-cell class="number" style="text-align: right;"><b>{{ $report['subtotal_total_selling_price'] }}</b></x-table-cell>
          </x-table-row>
        </tbody>
      </x-table>
      @endforeach
  </div>
  <x-table class="w-full table-fixed mt-4">
    <x-table-header>
      <x-table-row>
        <x-table-header-cell colspan="2" style="text-align: center;">{{ __('Grand Total') }}</x-table-header-cell>
      </x-table-row>
      <x-table-row>
        <x-table-header-cell>{{ __('Cost') }}</x-table-header-cell>
        <x-table-header-cell class="number text-right" style="text-align: right;"><b>{{ $footer['grand_total_initial_price'] }}</b></x-table-header-cell>
      </x-table-row>
      <x-table-row>
        <x-table-header-cell>{{ __('Selling price') }}</x-table-header-cell>
        <x-table-header-cell class="number text-right" style="text-align: right;"><b>{{ $footer['grand_total_selling_price'] }}</b></x-table-header-cell>
      </x-table-row>
    </x-table-header>
  </x-table>
</div>

