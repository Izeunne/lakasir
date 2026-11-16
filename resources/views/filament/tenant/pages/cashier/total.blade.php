@php
use function Filament\Support\format_money;
use App\Features\{SellingTax, Discount};

@endphp
<div class="space-y-2">
  <div class="flex items-center justify-between py-0.5">
    <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Sub total') }}</p>
    <p class="font-bold text-lakasir-primary">{{ price_format($sub_total) }}</p>
  </div>
  @feature(SellingTax::class)
    <div class="flex items-center justify-between py-0.5">
      <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Tax') }}</p>
      <p class="font-bold text-lakasir-primary">{{ $tax }}%</p>
    </div>
  @endfeature
  @feature(Discount::class)
    <div class="flex items-center justify-between py-0.5">
      <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Discount price') }}</p>
      <p class="font-bold text-lakasir-primary">({{ price_format($this->discount_price) }})</p>
    </div>
  @endfeature
  <hr class="border-gray-200 dark:border-gray-700 my-1" />
  <div class="flex items-center justify-between py-0.5">
    <p class="font-bold text-gray-900 dark:text-white text-sm">{{ __('Total') }}</p>
    <p class="font-bold text-lakasir-primary text-base" x-ref="total" data-value="{{ $total_price }}">{{ price_format($total_price) }}</p>
  </div>
  <div class="flex items-center justify-between py-0.5">
    <p class="font-bold text-gray-900 dark:text-white text-sm">{{ __('Money changes') }}</p>
    <p class="font-bold text-lakasir-primary text-base" x-ref="moneyChanges"></p>
  </div>
</div>
