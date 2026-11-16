@php
  use App\Features\{Member, Voucher, Discount};
@endphp

<div class="space-y-2.5">
  @feature(Member::class)
    <div class="flex items-center justify-between hover:underline cursor-pointer py-0.5"
      x-on:mousedown="$dispatch('open-modal', {
        id: 'edit-detail',
        inputId: 'memberSelect',
        index: 0,
        title: '@lang('Search member')'
      })">
      <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Member') }}</p>
      <p class="font-bold text-gray-800 dark:text-gray-100">{{ $cartDetail['member_label'] ?? __('No Member Selected') }}</p>
    </div>
  @endfeature
  @if($about && $about->business_type == 'fnb')
    <div class="flex items-center justify-between hover:underline cursor-pointer py-0.5"
        x-on:mousedown="$dispatch('open-modal', {
          id: 'modal-selected-table'
        })">
      <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Table') }}</p>
      <div class="font-bold text-gray-800 dark:text-gray-100">{!! $cartDetail['table_id'] ?? '-' !!}</div>
    </div>
  @endif
  @feature(Voucher::class)
  <div class="flex items-center justify-between py-0.5">
    <div>
      <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Voucher') }}</p>
      <div class="flex gap-x-2 mt-1.5">
        @if($cartDetail['voucher'] == null)
          @foreach($availableVoucher as $voucher)
            <x-filament::button wire:click="assignVoucher('{{ $voucher->code }}')" color="gray" size="xs">
              <x-heroicon-s-ticket class="h-4 w-4 mr-1"/>
              <span>{{ $voucher->code }}</span>
            </x-filament::button>
          @endforeach
        @endif
      </div>
    </div>
    <div class="flex items-center gap-x-2 font-bold text-gray-800 dark:text-gray-100">
      {{ $cartDetail['voucher'] ?? '-' }}
      @if($cartDetail['voucher'] != null)
        <button class="p-1 bg-red-100 hover:bg-red-200 text-red-600 rounded-md flex items-center transition" wire:click="removeVoucher">
          <x-heroicon-o-x-mark class="h-3.5 w-3.5 text-red-600"/>
        </button>
      @endif
    </div>
  </div>
  @endfeature
  @feature(Discount::class)
  <div class="flex items-center justify-between hover:underline cursor-pointer py-0.5"
      x-on:mousedown="$dispatch('open-modal', {
        id: 'edit-detail',
        inputId: 'discountInput',
        index: 3,
        title: '@lang('Discount')'
      })">
    <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Discount') }}</p>
    <div class="font-bold text-gray-800 dark:text-gray-100">{{ $cartDetail['discount_price'] ?? '-' }}</div>
  </div>
  @endfeature
</div>
