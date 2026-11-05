<div class="space-y-2">
  @forelse($cartItems as $item)
    @php($sub_total += $item->price)
    <div class="flex justify-between rounded-lg border bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
      <div class="flex items-center space-x-3">
        <div class="space-y-1">
          <p class="font-semibold">{{ $item->product->name }}</p>
          @if (!empty($item->selected_variations))
            <div class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
              @foreach ($item->selected_variations as $var)
                <div>- {{ $var['variation_name'] }}: {{ $var['option_name'] }}
                  @if (!empty($var['price']) && $var['price'] > 0)
                    <span class="font-medium text-lakasir-primary">(+{{ price_format($var['price']) }})</span>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
          @if (!empty($item->note))
            <div class="text-xs italic text-gray-500 dark:text-gray-400">
              {{ __('Note') }}: {{ $item->note }}
            </div>
          @endif
          <div class="flex space-x-3 text-xs text-gray-400">
            <p>{{ price_format($item->price / $item->qty) }} x {{ $item->qty }}</p>
          </div>
        </div>
      </div>
      <div class="flex items-center">
        <p class="font-semibold text-lakasir-primary">{{ $item->price_format_money }}</p>
      </div>
    </div>
  @empty
    <div class="flex justify-center items-center h-40 border bg-white rounded-lg dark:border-gray-900 dark:bg-gray-900">
      <x-heroicon-o-x-mark class="text-gray-900 dark:text-white h-10 w-10"/>
        <p class="text-3xl text-gray-600 dark:text-white">{{ __('No item') }}</p>
    </div>
  @endforelse
</div>

