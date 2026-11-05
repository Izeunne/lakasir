@php
  use Filament\Facades\Filament;
  use App\Features\{PaymentShortcutButton, SellingTax, Discount};

@endphp
<div class="">
  <div class="grid grid-cols-3 gap-x-4">
    <div class="col-span-2">
      {{ $this->table }}
    </div>
    <div class="fixed right-0 h-screen w-1/3 overflow-y-scroll pb-10">
      <div class="mt-4 h-screen space-y-2 px-4">
        <div class="flex items-center justify-between" x-data="fullscreen">
          <p class="text-xl font-semibold">{{ __('Orders details') }}</p>
          <div class="flex items-center">
            <div class="hidden items-center gap-x-2 xl:flex">
              <a href="/member/sellings"
                class="flex items-center justify-center gap-x-1 rounded-lg bg-gray-100 px-4 py-1 text-gray-500">
                <x-heroicon-o-arrow-left class="h-4 w-4 text-gray-500" />
                <p class="hidden lg:block">{{ __('Back') }} </p>
              </a>

              <button x-on:click="$dispatch('open-modal', {id: 'qr-scanner-modal'})" type="button"
                class="rounded-full p-2 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Scan with camera">
                <x-heroicon-o-qr-code class="h-8 w-8 text-gray-900 dark:text-gray-300" />
              </button>

            </div>
            <div class="gap-x-2">
              <x-filament::dropdown placement="top-start">
                <x-slot name="trigger">
                  <x-heroicon-o-ellipsis-vertical class="h-8 w-8 cursor-pointer text-gray-900 dark:text-gray-300" />
                </x-slot>

                <x-filament::dropdown.list>
                  <x-filament::dropdown.list.item x-on:mousedown="document.location.reload()">
                    <div class="flex gap-x-2">
                      <x-heroicon-m-arrow-path class="h-5 w-5 cursor-pointer text-gray-900 dark:text-gray-300" />
                      <p>{{ __('Reload') }} </p>
                    </div>
                  </x-filament::dropdown.list.item>

                  <x-filament::dropdown.list.item x-on:mousedown="requestFullscreen">
                    <div class="flex gap-x-2">
                      <x-heroicon-o-arrows-pointing-out
                        class="h-5 w-5 cursor-pointer text-gray-900 dark:text-gray-300" />
                      <p>{{ __('Fullscreen') }} </p>
                    </div>
                  </x-filament::dropdown.list.item>
                  <x-filament::dropdown.list.item>
                    <p class="flex gap-x-2" wire:confirm="Are you sure you want to clear all of the items?"
                      wire:click.prevent="clearCart">
                      <x-heroicon-o-trash class="h-5 w-5 cursor-pointer text-gray-900 dark:text-gray-300" />
                      <span>{{ __('Clear') }} </span>
                    </p>
                  </x-filament::dropdown.list.item>

                </x-filament::dropdown.list>
              </x-filament::dropdown>
            </div>
          </div>
        </div>
        <hr />
        <div class="hidden justify-between lg:flex">
          <p class="">{{ Filament::auth()->user()->cashier_name }}</p>
        </div>
        <div class="flex items-center justify-between">
          <p class="mb-2 hidden text-2xl font-semibold lg:block">{{ __('Current Orders') }}</p>
          <div class="flex gap-x-1"></div>
        </div>
        <div class="max-h-[35%] min-h-40 space-y-2 overflow-auto overflow-y-scroll" wire:loading.class="opacity-20"
          wire:target="addCart,addVariationItem,reduceCart,increaseCart,deleteCart,addDiscountPricePerItem,addCartUsingScanner,confirmAddVariationCart">
          @php
            $orderedCartItems = $cartItems
              ->groupBy('product_id')
              ->map(fn ($group) => $group->sortBy('id'))
              ->flatten(1);
          @endphp
          @forelse($orderedCartItems as $item)
            <div class="mb-4 rounded-lg border bg-white px-4 py-2 dark:border-gray-900 dark:bg-gray-900"
              style="margin-bottom: 14px;"
              id="cart-item-{{ $item->id }}" wire:key="cart-item-{{ $item->id }}">
              <div class="grid items-center space-x-3">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $item->product->name }}</p>
                    @if (!empty($item->selected_variations))
                      <div class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                        @foreach ($item->selected_variations as $var)
                          <div class="flex items-center gap-x-1">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $var['variation_name'] }}:</span>
                            <span>{{ $var['option_name'] }}</span>
                            @if (!empty($var['price']) && $var['price'] > 0)
                              <span class="font-semibold text-lakasir-primary">(+{{ price_format($var['price']) }})</span>
                            @endif
                          </div>
                        @endforeach
                      </div>
                    @endif
                  </div>
                  <p class="whitespace-nowrap font-semibold text-lakasir-primary">{{ $item->price_format_money }}</p>
                </div>
              </div>

              <div class="grid grid-cols-2 items-center space-y-2 py-2 text-right">
                <div class="col-span-2">
                  @feature(Discount::class)
                    <div class="mb-1 flex justify-end">
                      <x-filament::input.wrapper class="w-1/2">
                        <x-filament::input type="text" id="{{ $item->product->name }}-{{ $item->id }}"
                          value="{{ $item->discount_price == 0 ? '' : $item->discount_price }}"
                          wire:keyup.debounce.500ms="addDiscountPricePerItem({{ $item }}, parseFloat($event.target.value.replace(/,/g, '')))"
                          placeholder="{{ __('Discount') }}" class="w-1/2 text-right" inputMode="numeric"
                          x-mask:dynamic="$money($input)" />
                      </x-filament::input.wrapper>
                    </div>
                  @endfeature
                  @if ($item->discount_price && $item->discount_price > 0)
                    <p class="font-semibold text-lakasir-primary">{{ $item->final_price_format }}</p>
                  @endif
                </div>
              </div>

              <div class="flex h-8 items-center space-x-3">
                <button class="rounded-lg !bg-gray-100 px-2 py-1"
                  x-on:click="$wire.reduceCart({{ $item->id }});" wire:loading.attr="disabled">
                  <x-heroicon-o-minus-small class="h-4 w-4 !text-green-900" />
                </button>
                <x-filament::input.wrapper class="w-20" x-data="cart">
                  <x-filament::input type="text"
                    id="{{ $item->product->name }}-{{ $item->id }}-qty"
                    data-value="{{ $item->qty }}" value="{{ $item->qty }}"
                    x-on:keyup.debounce.500ms="(e) => add('{{ $item->id }}', e.target.value)"
                    placeholder="{{ __('Qty') }}" class="w-1/2 text-right" inputMode="numeric" />
                </x-filament::input.wrapper>
                <button class="rounded-lg !bg-lakasir-primary px-2 py-1"
                  wire:click.stop="increaseCart({{ $item->id }})" wire:loading.attr="disabled">
                  <x-heroicon-o-plus-small class="h-4 w-4 !text-white" />
                </button>
                <livewire:price-setting :cart-item="$item" key="{{ $item->id }}" />
                <button class="ml-auto rounded-lg !bg-danger-100 px-2 py-1" wire:click="deleteCart({{ $item->id }})"
                  wire:loading.attr="disabled">
                  <x-heroicon-o-trash class="h-4 w-4 !text-danger-900" />
                </button>
              </div>

              {{-- Catatan Per Item --}}
              <div class="mt-2" style="margin-top: 10px;">
                <x-filament::input.wrapper>
                  <x-slot name="prefix">
                    <x-heroicon-o-pencil-square class="h-4 w-4 text-gray-400" />
                  </x-slot>
                  <x-filament::input
                    type="text"
                    id="note-{{ $item->id }}"
                    value="{{ $item->note }}"
                    wire:keyup.debounce.500ms="updateCartItemNote({{ $item->id }}, $event.target.value)"
                    placeholder="{{ __('Catatan item (opsional)...') }}"
                    class="text-xs" />
                </x-filament::input.wrapper>
              </div>

              {{-- Tombol Buka Tutup & Opsi Variasi --}}
              @if ($item->product && $item->product->variations->isNotEmpty())
                @php
                  $hasMissing = $item->hasMissingRequiredVariations();
                  $shouldExpand = $hasMissing || ($expandedVariationCartItemId === $item->id);
                @endphp
                <div x-data="{ expanded: {{ $shouldExpand ? 'true' : 'false' }} }" class="mt-3 border-t pt-2 dark:border-gray-800">
                  <div class="flex items-center justify-between">
                    <button type="button" @click="expanded = !expanded"
                      class="flex items-center gap-1.5 text-xs font-semibold text-lakasir-primary hover:opacity-80">
                      <span x-text="expanded ? '{{ __('Tutup Variasi') }}' : '{{ __('Atur / Ubah Variasi') }}'"></span>
                      <x-heroicon-m-chevron-down class="h-3.5 w-3.5 transition-transform duration-200" ::class="expanded ? 'rotate-180' : ''" />
                    </button>

                    @if ($hasMissing)
                      <span class="rounded-full bg-danger-100 px-2 py-0.5 text-[10px] font-bold text-danger-700 dark:bg-danger-900/40 dark:text-danger-400">
                        {{ __('Wajib Dipilih') }}
                      </span>
                    @elseif (!empty($item->selected_variations))
                      <span class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ count($item->selected_variations) }} {{ __('terpilih') }}
                      </span>
                    @endif
                  </div>

                  {{-- Kontainer Pilihan Variasi yang bisa dibuka-tutup --}}
                  <div x-show="expanded" x-collapse class="mt-2 space-y-2.5 rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                    @foreach ($item->product->variations as $variation)
                      @php
                        $selectedOptionIds = collect($item->selected_variations ?? [])
                            ->where('variation_id', $variation->id)
                            ->pluck('option_id')
                            ->all();
                        $isVarMissing = $variation->is_required && empty($selectedOptionIds);
                      @endphp

                      <div class="space-y-1.5 rounded-md border p-2 transition-colors {{ $isVarMissing ? 'border-danger-300 bg-danger-50/40 dark:border-danger-800 dark:bg-danger-950/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800' }}">
                        <div class="flex items-center justify-between">
                          <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $variation->name }}</span>
                          @if ($variation->is_required)
                            <span class="text-[10px] font-bold {{ $isVarMissing ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">
                              * {{ __('Wajib') }}
                            </span>
                          @else
                            <span class="text-[10px] text-gray-400">{{ __('Opsional') }}</span>
                          @endif
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                          @foreach ($variation->options as $option)
                            @php
                              $isSelected = in_array($option->id, $selectedOptionIds);
                            @endphp

                            <button
                              type="button"
                              wire:click="toggleCartItemVariationOption({{ $item->id }}, {{ $variation->id }}, {{ $option->id }}, '{{ $variation->type }}')"
                              class="flex items-center gap-1 rounded-md px-2.5 py-1 text-xs transition-all {{ $isSelected ? 'bg-lakasir-primary text-white font-semibold shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200' }}">
                              <span>{{ $option->name }}</span>
                              @if ($option->price > 0)
                                <span class="text-[10px] opacity-80">(+{{ price_format($option->price) }})</span>
                              @endif
                            </button>
                          @endforeach

                          @if (!$variation->is_required && $variation->type === 'single' && !empty($selectedOptionIds))
                            <button
                              type="button"
                              wire:click="clearCartItemVariation({{ $item->id }}, {{ $variation->id }})"
                              class="rounded-md border border-gray-300 px-2 py-1 text-[11px] text-gray-500 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400">
                              {{ __('Kosongkan') }}
                            </button>
                          @endif
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>

                <div class="mt-2 pt-1" style="margin-top: 10px;">
                  <button
                    type="button"
                    wire:click="addVariationItem({{ $item->product_id }})"
                    wire:loading.attr="disabled"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-lakasir-primary/50 bg-lakasir-primary/5 px-4 py-2 text-xs font-semibold text-lakasir-primary transition hover:bg-lakasir-primary/15">
                    <x-heroicon-m-plus-circle class="h-4 w-4" />
                    <span>{{ __('Tambah Item Terpisah') }}</span>
                  </button>
                </div>
              @endif
            </div>
          @empty
            <div
              class="flex h-40 items-center justify-center rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-900">
              <x-heroicon-o-x-mark class="hidden h-10 w-10 text-gray-900 dark:text-white lg:block" />
              <p class="text-xl text-gray-600 dark:text-white lg:text-3xl">{{ __('No item') }}</p>
            </div>
          @endforelse
        </div>
        <div>
          <div
            class="w-full rounded-lg border bg-white px-4 py-2 text-gray-600 dark:border-gray-900 dark:bg-gray-900 dark:text-white">
            @include('filament.tenant.pages.cashier.detail')
          </div>
        </div>
        <div>
          <div
            class="w-full rounded-lg border bg-white px-4 py-2 text-gray-600 dark:border-gray-900 dark:bg-gray-900 dark:text-white">
            @include('filament.tenant.pages.cashier.total')
          </div>
        </div>
        @if ($this->hasMissingRequiredVariations())
          <button type="button" wire:click="notifyMissingVariations"
            class="flex w-full items-center justify-center gap-2 rounded-lg bg-gray-400 px-2 py-4 font-semibold text-white cursor-not-allowed">
            <x-heroicon-m-exclamation-triangle class="h-5 w-5 text-amber-300" />
            <span>{{ __('Pilih Variasi Wajib Dahulu') }}</span>
          </button>
        @else
          <button class="w-full rounded-lg bg-lakasir-primary px-2 py-4 font-semibold text-white transition hover:opacity-90"
            x-on:mousedown="$dispatch('open-modal', {id: 'proceed-the-payment'})">{{ __('Proceed to payment') }}</button>
        @endif
      </div>
    </div>
  </div>
  {{-- modal --}}
  <x-filament::modal id="edit-detail" width="2xl">
    <form wire:submit.prevent="storeCart">
      <x-slot name="heading">
        <p id="titleEditDetail">{{ __('Edit detail') }}</p>
      </x-slot>
      {{ $this->storeCartForm }}
      <x-filament::button type="submit" class="mt-10">
        {{ __('Save') }}
      </x-filament::button>
    </form>
  </x-filament::modal>
  <x-filament::modal id="proceed-the-payment" width="5xl">
    <form wire:submit.prevent="proceedThePayment">
      <div class="my-2 grid gap-x-4 md:grid-cols-2">
        <div x-data="detail">
          <div class="rounded-lg">
            <div class="mb-4 grid grid-cols-4 gap-1">
              <template x-for="paymentMethod in paymentMethods">
                <div
                  x-on:click="cartDetail['payment_method_id'] = paymentMethod.id; $wire.cartDetail['payment_method_id'] = paymentMethod.id;"
                  class="flex cursor-pointer justify-center rounded-md border-none px-4 py-2 text-sm hover:scale-105 dark:text-white"
                  :class="cartDetail['payment_method_id'] == paymentMethod.id ? 'bg-lakasir-primary text-white' :
                      'dark:bg-gray-900 bg-gray-300 '"
                  x-text="paymentMethod.name.substring(0, 8)">
                </div>
              </template>
            </div>
            <x-filament::input.wrapper
              x-show="paymentMethods.filter((pm) => pm.is_credit)[0]?.id == cartDetail['payment_method_id']"
              :valid="!$errors->has('due_date')" class="mb-2">
              <x-slot name="prefix">
                {{ __('Due date') }}
              </x-slot>
              <x-filament::input type="date" wire:model="cartDetail.due_date" />
            </x-filament::input.wrapper>
            <div class="mb-4">
              @include('filament.tenant.pages.cashier.total')
            </div>
            @error('payed_money')
              <span class="error text-danger-500">{{ $message }}</span>
            @enderror
            <input id="display"
              class="@error('payed_money') 'border-danger-500' @enderror w-full rounded-md border border-gray-300 bg-white p-2 text-right text-lg text-black dark:bg-gray-900 dark:text-white"
              focus :disabled="isTouchScreen" x-mask:dynamic="$money($input)" x-on:keyup="changes" x-ref="payedMoney"
              inputMode="numeric">
            <div class="mt-4 grid grid-cols-3 gap-4" id="calculator-button-shortcut">
            </div>
            <div class="mt-2 grid grid-cols-3 gap-2 lg:mt-2 lg:gap-2" id="calculator-button">
              <button type="button" class="col-span-3 rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append('no_changes')">{{ __('No change') }}</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(7)">7</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(8)">8</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(9)">9</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(4)">4</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(5)">5</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(6)">6</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(1)">1</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(2)">2</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(3)">3</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append('.')">.</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append(0)">0</button>
              <button type="button"
                class="flex items-center justify-center rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                x-on:click="append('backspace')">
                <x-filament::icon icon="heroicon-o-backspace" class="h-5 w-5 text-gray-500 dark:text-white" />
              </button>
              <div class="col-span-3 flex gap-x-2">
                <button wire:loading.attr="disabled" type="submit"
                  class="flex w-full items-center justify-center gap-x-2 rounded-md bg-lakasir-primary p-2 text-lg text-white hover:bg-[#ff6611]">
                  <div wire:loading>
                    <x-filament::loading-indicator class="h-5 w-5" />
                  </div>
                  {{ __('Pay it') }}
                </button>
                <button wire:click="dispatch('close-modal', {id: 'proceed-the-payment'});" type="button"
                  class="flex w-full items-center justify-center gap-x-2 rounded-md bg-gray-300 p-2 text-lg">
                  {{ __('Close') }}
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="hidden max-h-[80vh] overflow-y-scroll md:block">
          @if ($errors->any())
            @foreach ($errors->all() as $error)
              <p class="error w-full text-center text-lg text-danger-500">{{ $error }}</p>
            @endforeach
          @endif
          @include('filament.tenant.pages.cashier.items')
        </div>
      </div>
    </form>
  </x-filament::modal>
  <x-filament::modal id="success-modal" width="xl" :close-by-clicking-away="false" :close-by-escaping="false">
    <div class="flex flex-col items-center justify-center">
      <x-heroicon-o-check-circle style="color: rgb(34 197 94); width: 200px" />
      <p class="">@lang('Success')</p>
      <p class="text-3xl font-bold">
        @lang('Change'):
        <span id="changes"></span>
      </p>
    </div>
    <x-slot name="footer">
      <div class="grid grid-cols-2 gap-x-2">
        <x-filament::button icon="heroicon-m-printer" id="printReceiptButton">
          {{ __('Print') }}
        </x-filament::button>
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'success-modal'})">
          {{ __('Close') }}
        </x-filament::button>
      </div>
    </x-slot>
  </x-filament::modal>
  <x-filament::modal id="modal-selected-table" width="xl" :close-by-clicking-away="false" :close-by-escaping="false">
    <div class="grid grid-cols-4 gap-4">
      @foreach ($tableOption as $table)
        <div x-on:click="$wire.cartDetail['table_id'] = {{ $table->id }};"
          class="flex cursor-pointer justify-center rounded-md border border-lakasir-primary px-4 py-2 text-sm hover:scale-105 dark:text-white"
          :class="$wire.cartDetail['table_id'] == {{ $table->id }} ? 'bg-lakasir-primary text-white' : 'dark:bg-gray-900 '">
          {{ $table->number }}
        </div>
      @endforeach
    </div>
    <x-slot name="footer">
      <x-slot name="heading">
        <p id="titleEditDetail">{{ __('Choose the table') }}</p>
      </x-slot>
      <div class="grid grid-cols-2 gap-x-2">
        <x-filament::button id="saveSelectedTable"
          x-on:click="$dispatch('close-modal', {id: 'modal-selected-table'}); $wire.storeCart()">
          {{ __('Save') }}
        </x-filament::button>
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'modal-selected-table'})">
          {{ __('Close') }}
        </x-filament::button>
      </div>
    </x-slot>
  </x-filament::modal>

  <x-filament::modal id="variation-modal" width="xl" :close-by-clicking-away="false" :close-by-escaping="true">
    <x-slot name="header">
      @if ($selectedProductForVariation)
        <div class="flex items-center justify-between pr-6">
          <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $selectedProductForVariation->name }}</h2>
            <p class="text-sm font-semibold text-lakasir-primary">
              {{ price_format($selectedProductForVariation->selling_price) }}
              @if ($selectedProductForVariation->unit)
                <span class="text-xs font-normal text-gray-400">/ {{ $selectedProductForVariation->unit }}</span>
              @endif
            </p>
          </div>
          @if ($selectedProductForVariation->hero_image)
            <img src="{{ $selectedProductForVariation->hero_image }}" class="h-14 w-14 rounded-lg border object-cover dark:border-gray-700" alt="{{ $selectedProductForVariation->name }}">
          @endif
        </div>
      @else
        <div class="h-6"></div>
      @endif
    </x-slot>

    @if ($selectedProductForVariation)
      <div class="max-h-[60vh] space-y-5 overflow-y-auto px-1 py-2">
        @foreach ($selectedProductForVariation->variations as $variation)
          <div class="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/40">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-base font-bold text-gray-900 dark:text-white">{{ $variation->name }}</span>
                @if ($variation->is_required)
                  <span class="rounded-full bg-danger-100 px-2 py-0.5 text-xs font-semibold text-danger-700 dark:bg-danger-900/40 dark:text-danger-400">
                    {{ __('Wajib') }}
                  </span>
                @else
                  <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-normal text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    {{ __('Opsional') }}
                  </span>
                @endif
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400">
                {{ $variation->type === 'multiple' ? __('Bisa pilih lebih dari satu') : __('Pilih 1') }}
              </span>
            </div>

            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
              @foreach ($variation->options as $option)
                @php
                  $isSelected = false;
                  if ($variation->type === 'single') {
                      $isSelected = ($selectedVariationOptions[$variation->id] ?? null) == $option->id;
                  } else {
                      $isSelected = in_array($option->id, $selectedVariationOptions[$variation->id] ?? []);
                  }
                @endphp

                <button
                  type="button"
                  wire:click="selectVariationOption({{ $variation->id }}, {{ $option->id }}, '{{ $variation->type }}')"
                  class="flex items-center justify-between rounded-lg border p-3 text-left transition-all duration-150 {{ $isSelected ? 'border-lakasir-primary bg-lakasir-primary/10 font-semibold text-lakasir-primary ring-1 ring-lakasir-primary' : 'border-gray-200 bg-white text-gray-800 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' }}">
                  <div class="flex items-center gap-2">
                    <div class="flex h-4 w-4 items-center justify-center border rounded-{{ $variation->type === 'single' ? 'full' : 'md' }} {{ $isSelected ? 'border-lakasir-primary bg-lakasir-primary text-white' : 'border-gray-400 bg-white dark:bg-gray-700' }}">
                      @if ($isSelected)
                        @if ($variation->type === 'single')
                          <div class="h-1.5 w-1.5 rounded-full bg-white"></div>
                        @else
                          <x-heroicon-m-check class="h-3 w-3" />
                        @endif
                      @endif
                    </div>
                    <span class="text-sm">{{ $option->name }}</span>
                  </div>
                  <span class="text-xs font-semibold {{ $option->price > 0 ? 'text-lakasir-primary' : 'text-gray-400' }}">
                    {{ $option->price > 0 ? '+ ' . price_format($option->price) : 'Gratis' }}
                  </span>
                </button>
              @endforeach
            </div>
          </div>
        @endforeach

        {{-- Jumlah Pesanan --}}
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
          <div>
            <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ __('Jumlah') }}</span>
            <p class="text-xs text-gray-500">{{ __('Tentukan jumlah porsi/item') }}</p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              wire:click="decrementVariationQty"
              class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-200 font-bold text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
              <x-heroicon-o-minus class="h-4 w-4" />
            </button>
            <span class="w-8 text-center text-lg font-bold text-gray-900 dark:text-white">{{ $variationQty }}</span>
            <button
              type="button"
              wire:click="incrementVariationQty"
              class="flex h-8 w-8 items-center justify-center rounded-lg bg-lakasir-primary font-bold text-white hover:opacity-90">
              <x-heroicon-o-plus class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    @endif

    <x-slot name="footer">
      <div class="flex w-full items-center justify-between">
        <div class="text-left">
          <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total Harga') }}</p>
          <p class="text-xl font-extrabold text-lakasir-primary">
            {{ price_format($this->variationTotalPrice) }}
          </p>
        </div>
        <div class="flex gap-2">
          <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'variation-modal'})">
            {{ __('Batal') }}
          </x-filament::button>
          <x-filament::button
            wire:click="confirmAddVariationCart"
            :disabled="!$this->canSubmitVariations"
            icon="heroicon-o-shopping-bag">
            {{ __('Tambah ke Keranjang') }}
          </x-filament::button>
        </div>
      </div>
    </x-slot>
  </x-filament::modal>

  <x-filament::modal id="qr-scanner-modal" width="lg" :close-by-clicking-away="false"
    x-on:close-modal.window="if ($event.detail.id === 'qr-scanner-modal') { window.stopScanner(); }">
    <x-slot name="heading">
      {{ __('Scan Barcode with Camera') }}
    </x-slot>

    {{-- Main container with Alpine.js state management --}}
    <div x-data="{ isLoading: false }" x-ref="scannerContainer">

      {{-- Loading spinner (hidden by default) --}}
      <div x-show="isLoading" class="flex min-h-[300px] flex-col items-center justify-center text-center">
        <svg class="h-16 w-16 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none"
          viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
          </circle>
          <path class="opacity-75" fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
          </path>
        </svg>
        <p class="mt-4 text-lg font-medium text-gray-600 dark:text-gray-300">
          Processing product...
        </p>
      </div>

      {{-- QR Scanner container (hidden when loading) --}}
      <div x-show="!isLoading">
        <div wire:ignore id="qr-reader" class="w-full"></div>
      </div>

    </div>

    <x-slot name="footer">
      <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'qr-scanner-modal'})">
        {{ __('Close') }}
      </x-filament::button>
    </x-slot>
  </x-filament::modal>

  <style>
    /* html5-qrcode library button & control styling */
    #qr-reader__dashboard_section_csr button,
    #qr-reader__dashboard_section_swaplink {
      background-color: #f97316 !important;
      border: none !important;
      color: #fff !important;
      padding: 0.5rem 1.25rem !important;
      border-radius: 0.5rem !important;
      font-weight: 600 !important;
      font-size: 0.875rem !important;
      cursor: pointer !important;
      transition: background-color 0.2s ease !important;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1) !important;
      margin: 0.25rem 0 !important;
    }

    #qr-reader__dashboard_section_csr button:hover,
    #qr-reader__dashboard_section_swaplink:hover {
      background-color: #ea580c !important;
    }

    #qr-reader__dashboard_section {
      padding: 0.75rem !important;
      margin-top: 0.5rem !important;
    }

    #qr-reader__dashboard_section_csr {
      display: flex !important;
      flex-direction: column !important;
      gap: 0.5rem !important;
      align-items: stretch !important;
    }

    #qr-reader__dashboard_section_csr select {
      width: 100% !important;
      padding: 0.5rem 0.75rem !important;
      border-radius: 0.5rem !important;
      border: 1px solid #d1d5db !important;
      background-color: #fff !important;
      color: #111827 !important;
      font-size: 0.875rem !important;
      outline: none !important;
      transition: border-color 0.2s ease !important;
    }

    #qr-reader__dashboard_section_csr select:focus {
      border-color: #f97316 !important;
      box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.25) !important;
    }

    #qr-reader__scan_region {
      min-height: 200px !important;
      border-radius: 0.5rem !important;
      overflow: hidden !important;
    }

    #qr-reader {
      border: none !important;
    }

    #qr-reader__dashboard {
      padding: 0.5rem !important;
    }

    #qr-reader__status_line {
      font-size: 0.875rem !important;
      padding: 0.25rem 0.5rem !important;
    }

    /* Dark mode support */
    .dark #qr-reader__dashboard_section_csr select {
      background-color: #111827 !important;
      color: #f3f4f6 !important;
      border-color: #374151 !important;
    }

    .dark #qr-reader__dashboard_section_csr select:focus {
      border-color: #f97316 !important;
      box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.25) !important;
    }

    .dark #qr-reader__dashboard_section {
      background-color: #1f2937 !important;
      border-color: #374151 !important;
    }

    .dark #qr-reader__dashboard {
      background-color: #111827 !important;
    }

    .dark #qr-reader__status_line {
      color: #d1d5db !important;
    }

    .dark #qr-reader img[alt="Info icon"] {
      filter: invert(1) !important;
    }

    /* Fix dark mode swap link: transparent background keeps text readable */
    .dark #qr-reader__dashboard_section_swaplink {
      background-color: transparent !important;
      color: #f97316 !important;
      text-decoration: underline !important;
      box-shadow: none !important;
      padding: 0 !important;
    }
  </style>




</div>

@script()
  <script>
    window.lakasirCurrency = @js($currency);
    window.lakasirLocale = @js($locale);
    let selling = null;
    $wire.on('selling-created', (event) => {
      selling = event.selling;
      $wire.dispatch('close-modal', {
        id: 'proceed-the-payment'
      });

      $wire.dispatch('open-modal', {
        id: 'success-modal',
        money_changes: selling.money_changes
      });
      setTimeout(() => {
        document.getElementById('changes').innerHTML = moneyFormat(selling.money_changes);
      }, 300);
    });
    document.getElementById("printReceiptButton").addEventListener('click', async (event) => {
      let about = @js($about);
      const printerData = getPrinter();

      try {
        if (!printerData) {
          new FilamentNotification()
            .title('@lang('You should choose the printer first in printer setting')')
            .danger()
            .actions([
              new FilamentNotificationAction('Setting')
              .icon('heroicon-o-cog-6-tooth')
              .button()
              .url('/member/printer'),
            ])
            .send()
        } else {
          const printer = new Printer(printerData.printerId);
          let printerAction = printer.font('a');
          if (about != undefined || about != null) {
            printerAction.size(1)
              .align('center')
              .text(about.shop_name)
              .size(0)
              .text(about.shop_location);
            if (printerData.header != undefined) {
              printerAction
                .text(printerData.header);
            }
            printerAction.align('left')
              .text('-------------------------------');
          }
          printerAction.table(['@lang('Cashier')', selling.user.name])
          if (selling.table != undefined && selling.table != null) {
            printerAction.table(['@lang('Table')', selling.table.number])
          }
          printerAction.table(['@lang('Payment method')', selling.payment_method.name]);
          if (selling.member != undefined && selling.member != null) {
            printerAction
              .table(['Member', selling.member.name]);
          }
          printerAction
            .text('-------------------------------');
          selling.selling_details.forEach(sellingDetail => {
            let price = sellingDetail.price;
            let text = moneyFormat(sellingDetail.price / sellingDetail.qty) + ' x ' + sellingDetail.qty
              .toString();
            printerAction.table([sellingDetail.product.name, moneyFormat(sellingDetail.price / sellingDetail
              .qty) + ' x ' + sellingDetail.qty.toString()])
            if (sellingDetail.selected_variations && sellingDetail.selected_variations.length > 0) {
              sellingDetail.selected_variations.forEach(v => {
                let extra = v.price > 0 ? ` (+${moneyFormat(v.price)})` : '';
                printerAction.text(`  - ${v.variation_name}: ${v.option_name}${extra}`);
              });
            }
            if (sellingDetail.note) {
              printerAction.text(`  * ${sellingDetail.note}`);
            }
            if (sellingDetail.discount_price > 0) {
              price = price - sellingDetail.discount_price;
              printerAction
                .align('right')
                .text(`(${moneyFormat(sellingDetail.discount_price)})`)
            }
            printerAction
              .align('right')
              .text(moneyFormat(price))
              .align('left')
          });
          printerAction
            .text('-------------------------------');
          if ("@js(feature(SellingTax::class))" == 'true') {
            printerAction.table(['@lang('Tax')', `${selling.tax}%`])
              .table(['@lang('Tax price')', moneyFormat(selling.tax_price)]);
          }
          printerAction
            .table(['@lang('Subtotal')', moneyFormat(selling.total_price)])
          if ("@js(feature(Discount::class))" == 'true') {
            printerAction
              .table(['@lang('Discount')',
                `(${moneyFormat(selling.total_discount_per_item + selling.discount_price)})`
              ])
          }
          printerAction
            .table(['@lang('Total price')', moneyFormat(selling.grand_total_price)])
            .text('-------------------------------')
            .table(['@lang('Payed money')', moneyFormat(selling.payed_money)])
            .table(['@lang('Change')', moneyFormat(selling.money_changes)])
            .align('center');

          if (printerData.footer != undefined) {
            printerAction
              .text(printerData.footer);
          }

          await printerAction
            .cut()
            .print();
        }
      } catch (error) {
        console.error(error);
      }
    });

    Alpine.data('fullscreen', () => {
      return {
        isFullscreen: false,
        requestFullscreen() {
          if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
            isFullscreen = true;
          } else {
            document.exitFullscreen();
            isFullscreen = false;
          }
        }
      }
    });
    Alpine.data('detail', () => {
      return {
        isTouchScreen() {
          return ('ontouchstart' in window) ||
            (navigator.maxTouchPoints > 0) ||
            (navigator.msMaxTouchPoints > 0);
        },
        displayValue: '',
        paymentMethods: $wire.entangle('paymentMethods'),
        cartDetail: @js($cartDetail),
        subtotal: $wire.entangle('total_price'),
        shortcut(number) {
          this.$refs.payedMoney.value = moneyFormat(number);
          this.changes();
          return;
        },
        append(number) {
          if (number == 'no_changes') {
            this.$refs.payedMoney.value = moneyFormat(this.subtotal);
            this.changes();
            return;
          }
          if (number == 'backspace') {
            this.displayValue = this.displayValue.slice(0, -1);
            this.$refs.payedMoney.value = moneyFormat(this.displayValue);
            this.changes();
            return;
          }
          this.displayValue += number;
          this.$refs.payedMoney.value = moneyFormat(this.displayValue);
          this.changes();
        },
        changes() {
          let val = this.$refs.payedMoney.value || '';
          let numericValue = val.replace(/\D/g, '');
          let num = parseInt(numericValue, 10);
          num = isNaN(num) ? 0 : num;

          this.displayValue = num > 0 ? num.toString() : '';

          $wire.cartDetail['money_changes'] = num - (this.subtotal);
          $wire.cartDetail['payed_money'] = num;

          if (this.$refs.moneyChanges) {
            this.$refs.moneyChanges.textContent = moneyFormat($wire.cartDetail['money_changes']);
          }
        }
      }
    });

    Alpine.data('cart', () => {
      return {
        add: (cartItemId, amount) => {
          $wire.updateCartItemQty(cartItemId, amount ?? 0)
        }
      }
    })

    let barcodeData = '';
    let barcodeTimeout;
    let scannerEnabled = true;
    let modalOpened = false;
    let input;
    let index;

    function generateSuggestedPayments(totalPrice) {
      const denominations = [500, 1000, 2000, 5000, 10000, 20000, 50000, 100000];
      const suggestions = [];

      for (let denom of denominations) {
        const suggestion = Math.ceil(totalPrice / denom) * denom;
        if (!suggestions.includes(suggestion)) {
          suggestions.push(suggestion);
        }
      }

      suggestions.sort((a, b) => a - b);

      return suggestions;
    }

    function generateButton(totalPrice) {
      const shortcutSuggestion = generateSuggestedPayments(totalPrice);
      let calculatorBtn = document.getElementById('calculator-button-shortcut');
      calculatorBtn.innerHTML = '';

      for (let suggestion of shortcutSuggestion) {
        const button = document.createElement('button');
        button.textContent = numberFormat(suggestion);
        button.setAttribute('type', 'button')
        button.setAttribute('x-on:click', `shortcut(${suggestion})`);
        button.className = 'bg-gray-300 hover:bg-gray-400 p-2 rounded-md text-lg';
        calculatorBtn.appendChild(button);
      }
    }

    $wire.on('open-modal', (event) => {

      // Initialize QR scanner when modal opens
      if (event.id === 'qr-scanner-modal') {
        // Create scanner instance only once (singleton pattern)
        if (!html5QrcodeScanner) {
          html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader",
            {
              fps: 10,
              qrbox: { width: 300, height: 200 },
              rememberLastUsedCamera: true
            },
            false // verbose mode disabled
          );
        }
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
      }


      if (event.inputId != undefined) {
        let inputId = event.inputId;
        let title = event.title;
        let titleModal = document.getElementById("titleEditDetail");
        titleModal.innerHTML = title;
        index = event.index;
        input = document.getElementById(inputId);
        const result = [...(input.parentNode.parentNode.parentNode.parentNode.parentNode.children)].forEach((child,
          i) => {
          if (i != index) {
            child.classList.add('hidden');
          }
        });
        input.classList.remove('hidden');
      }
      let totalPrice = $refs.total.getAttribute('data-value');
      if ("@js(feature(PaymentShortcutButton::class))" == 'true') {
        generateButton(totalPrice);
      }
      modalOpened = true;
    });

    $wire.on('close-modal', (event) => {
      if (input != undefined) {
        let titleModal = document.getElementById("titleEditDetail");
        titleModal.innerHTML = '@lang('Edit detail')';
        const result = [...(input.parentNode.parentNode.parentNode.parentNode.parentNode.children)].forEach((child,
          i) => {
          if (i != index) {
            child.classList.remove('hidden');
          }
        });
        input.classList.add('hidden');
        input = undefined
      }
      modalOpened = false;
    });

    // QR Scanner global variables and functions
    let html5QrcodeScanner = null;
    let isScanningEnabled = true;

    /**
     * Handles successful barcode/QR code scan
     * @param {string} decodedText - The decoded string from the QR code or barcode
     */
    async function onScanSuccess(decodedText, decodedResult) {
      if (!isScanningEnabled) return;

      // Find Alpine.js component for state management
      const readerElement = document.getElementById('qr-reader');
      if (!readerElement) {
        console.error('Scanner reader element not found!');
        return;
      }

      const alpineContainer = readerElement.closest('[x-ref="scannerContainer"]');
      if (!alpineContainer || !alpineContainer._x_dataStack) {
        console.error('Could not find the Alpine.js scanner container.');
        return;
      }
      const alpineComponent = alpineContainer._x_dataStack[0];

      // Disable scanning and show loading spinner
      isScanningEnabled = false;
      alpineComponent.isLoading = true;

      console.log(`Scan result: ${decodedText}`);

      // Process product and wait for Livewire to complete
      await $wire.call('addCartUsingScanner', decodedText);

      // Hide loading spinner
      alpineComponent.isLoading = false;

      // Show success notification
      new FilamentNotification()
        .title('Product added')
        .success()
        .duration(3000)
        .send();

      // Re-enable scanning after cooldown period
      setTimeout(() => {
        isScanningEnabled = true;
      }, 1000);
    }

    /**
     * Handles scan failure (empty implementation)
     */
    function onScanFailure(error) {
      // Intentionally empty - failures are handled silently
    }

    /**
     * Safely stops the camera scanner
     */
    window.stopScanner = () => {
      if (html5QrcodeScanner && html5QrcodeScanner.getState() === Html5QrcodeScannerState.SCANNING) {
        html5QrcodeScanner.clear().then(() => {
          console.log('QR Code scanner stopped successfully.');
        }).catch(err => {
          // Ignore errors during rapid closing
        });
      }
    };
    // Physical barcode scanner support (keyboard input)
    document.addEventListener('keypress', (event) => {
      if (modalOpened || !scannerEnabled) {
        return;
      }

      if (barcodeTimeout) {
        clearTimeout(barcodeTimeout);
      }

      if (event.key === 'Enter') {
        console.log('Barcode scanned:', barcodeData);
        $wire.addCartUsingScanner(barcodeData);

        barcodeData = '';
        scannerEnabled = false;

        // Re-enable scanner after processing
        setTimeout(() => {
          scannerEnabled = true;
        }, 1000);
      } else {
        barcodeData += event.key;
      }

      // Clear barcode data if no input for 500ms
      barcodeTimeout = setTimeout(() => {
        barcodeData = '';
      }, 500);
    });
  </script>
@endscript
