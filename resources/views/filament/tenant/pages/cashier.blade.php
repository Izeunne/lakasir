@php
  use Filament\Facades\Filament;
  use App\Features\{PaymentShortcutButton, SellingTax, Discount};

@endphp
<div class="p-2 sm:p-4 min-h-screen" x-data="posApp()">
  {{-- Mobile Top Navigation Switcher (< 480px) --}} <div
    class="pos-mobile-switcher mb-3 flex items-center justify-between gap-2 md:hidden">
    <div class="inline-flex rounded-lg bg-gray-200/90 p-1 dark:bg-gray-800">
      <button type="button" @click="mobileTab = 'products'"
        :class="mobileTab === 'products' ? 'bg-white shadow text-gray-900 font-bold dark:bg-gray-700 dark:text-white' : 'text-gray-600 dark:text-gray-400'"
        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs transition">
        <x-heroicon-o-squares-2x2 class="h-4 w-4" />
        <span>{{ __('Katalog') }}</span>
      </button>
      <button type="button" @click="mobileTab = 'cart'"
        :class="mobileTab === 'cart' ? 'bg-white shadow text-lakasir-primary font-bold dark:bg-gray-700' : 'text-gray-600 dark:text-gray-400'"
        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs transition relative">
        <x-heroicon-o-shopping-bag class="h-4 w-4" />
        <span>{{ __('Pesanan') }}</span>
        <span x-show="totalCartQty > 0" x-text="totalCartQty"
          class="rounded-full bg-lakasir-primary text-[10px] text-white px-1.5 py-0.5 font-bold leading-none">
        </span>
      </button>
    </div>
</div>

<style>
  @media (min-width: 480px) {
    .pos-mobile-switcher {
      display: none !important;
    }

    .pos-main-layout {
      display: flex !important;
      flex-direction: row !important;
      align-items: flex-start !important;
      gap: 0.625rem !important;
    }

    .pos-catalog-col {
      display: block !important;
      width: 54% !important;
      flex: 1 1 54% !important;
      min-width: 0 !important;
    }

    .pos-cart-col {
      display: flex !important;
      width: 46% !important;
      flex: 0 0 46% !important;
      max-height: calc(100vh - 1rem) !important;
      position: sticky !important;
      top: 0.5rem !important;
    }
  }

  @media (min-width: 768px) {
    .pos-main-layout {
      gap: 1rem !important;
    }

    .pos-catalog-col {
      width: 58% !important;
      flex: 1 1 58% !important;
    }

    .pos-cart-col {
      width: 42% !important;
      flex: 0 0 42% !important;
    }
  }

  @media (min-width: 1024px) {
    .pos-catalog-col {
      width: 62% !important;
      flex: 1 1 62% !important;
    }

    .pos-cart-col {
      width: 38% !important;
      flex: 0 0 38% !important;
    }
  }

  @media (min-width: 1280px) {
    .pos-catalog-col {
      width: 66.666667% !important;
      flex: 1 1 66.666667% !important;
    }

    .pos-cart-col {
      width: 33.333333% !important;
      flex: 0 0 33.333333% !important;
    }
  }

  /* POS Product Card: Full Width Edge-to-Edge Image */
  .fi-ta-content-grid .fi-ta-record {
    border-radius: 0.75rem !important;
    border: 1px solid rgba(229, 231, 235, 0.9) !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
    overflow: hidden !important;
    background-color: #ffffff !important;
    display: flex !important;
    flex-direction: column !important;
  }

  .dark .fi-ta-content-grid .fi-ta-record {
    border-color: rgba(55, 65, 81, 0.8) !important;
    background-color: #1f2937 !important;
  }

  .fi-ta-content-grid .fi-ta-record > div {
    width: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    height: 100% !important;
  }

  .fi-ta-content-grid .fi-ta-record div.py-4 {
    padding-top: 0 !important;
    padding-bottom: 0.75rem !important;
  }

  .fi-ta-content-grid .fi-ta-record .flex-1 {
    width: 100% !important;
  }

  .fi-ta-content-grid .fi-ta-record .flex-1 > button,
  .fi-ta-content-grid .fi-ta-record .flex-1 > a,
  .fi-ta-content-grid .fi-ta-record .flex-1 > div {
    padding-left: 0 !important;
    padding-right: 0 !important;
    padding-top: 0 !important;
    width: 100% !important;
    display: block !important;
  }

  .fi-ta-content-grid .fi-ta-record .flex-col.items-start {
    align-items: stretch !important;
    width: 100% !important;
  }

  .fi-ta-content-grid .fi-ta-record .fi-ta-col-wrp {
    width: 100% !important;
  }

  .fi-ta-content-grid .fi-ta-record .fi-ta-image {
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    display: block !important;
    overflow: hidden !important;
  }

  .fi-ta-content-grid .fi-ta-record .fi-ta-image > div,
  .fi-ta-content-grid .fi-ta-record .fi-ta-image .flex {
    width: 100% !important;
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .fi-ta-content-grid .fi-ta-record .fi-ta-image img {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    height: 130px !important;
    min-height: 130px !important;
    max-height: 130px !important;
    object-fit: cover !important;
    border-top-left-radius: 0.75rem !important;
    border-top-right-radius: 0.75rem !important;
    border-bottom-left-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
    display: block !important;
  }

  .pos-product-img-box {
    margin-left: -1rem !important;
    margin-right: -1rem !important;
    margin-top: -1rem !important;
    margin-bottom: 0.5rem !important;
    width: calc(100% + 2rem) !important;
    height: 130px !important;
    min-height: 130px !important;
    max-height: 130px !important;
    overflow: hidden !important;
    border-top-left-radius: 0.75rem !important;
    border-top-right-radius: 0.75rem !important;
    background-color: #f3f4f6;
  }

  .dark .pos-product-img-box {
    background-color: #374151 !important;
  }

  .pos-product-image {
    width: 100% !important;
    min-width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    display: block !important;
    border-top-left-radius: 0.75rem !important;
    border-top-right-radius: 0.75rem !important;
  }

  .fi-ta-content-grid .fi-ta-record .fi-ta-text {
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
  }

  .fi-ta-content-grid .fi-ta-record .fi-ta-actions {
    padding-left: 0.75rem !important;
    padding-right: 0.75rem !important;
    margin-top: auto !important;
  }

  /* POS View Mode: Grid */
  .pos-view-grid .fi-ta-content-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 0.75rem !important;
  }

  @media (min-width: 768px) {
    .pos-view-grid .fi-ta-content-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
  }

  @media (min-width: 1024px) {
    .pos-view-grid .fi-ta-content-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    }
  }

  /* POS View Mode: List */
  .pos-view-list .fi-ta-content-grid {
    display: grid !important;
    grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
    gap: 0.5rem !important;
  }

  .pos-view-list .fi-ta-content-grid .fi-ta-record {
    padding: 0.5rem 0.75rem !important;
  }

  .pos-view-list .fi-ta-content-grid .fi-ta-record > div {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 0.75rem !important;
  }

  .pos-view-list .fi-ta-content-grid .fi-ta-record > div > div:first-child {
    flex: 1 1 0% !important;
    min-width: 0 !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    gap: 0.75rem !important;
  }

  .pos-view-list .pos-product-img-box {
    width: 52px !important;
    min-width: 52px !important;
    max-width: 52px !important;
    height: 52px !important;
    min-height: 52px !important;
    max-height: 52px !important;
    margin: 0 !important;
    border-radius: 0.5rem !important;
    flex-shrink: 0 !important;
  }

  .pos-view-list .pos-product-img-box img {
    border-radius: 0.5rem !important;
  }

  .pos-view-list .fi-ta-content-grid .fi-ta-record .fi-ta-text {
    padding-left: 0 !important;
    padding-right: 0 !important;
    min-height: unset !important;
  }

  .pos-view-list .fi-ta-content-grid .fi-ta-record .fi-ta-actions {
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-top: 0 !important;
    flex-shrink: 0 !important;
  }

  /* POS View Mode: Small Icon (Compact) */
  .pos-view-compact .fi-ta-content-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 0.5rem !important;
  }

  @media (min-width: 768px) {
    .pos-view-compact .fi-ta-content-grid {
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    }
  }

  @media (min-width: 1024px) {
    .pos-view-compact .fi-ta-content-grid {
      grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
    }
  }

  @media (min-width: 1280px) {
    .pos-view-compact .fi-ta-content-grid {
      grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
    }
  }

  .pos-view-compact .fi-ta-content-grid .fi-ta-record {
    padding: 0.5rem !important;
    text-align: center !important;
  }

  .pos-view-compact .pos-product-img-box {
    width: 52px !important;
    min-width: 52px !important;
    max-width: 52px !important;
    height: 52px !important;
    min-height: 52px !important;
    max-height: 52px !important;
    margin: 0.125rem auto 0.375rem auto !important;
    border-radius: 0.625rem !important;
  }

  .pos-view-compact .pos-product-img-box img {
    border-radius: 0.625rem !important;
  }

  .pos-view-compact .fi-ta-content-grid .fi-ta-record .fi-ta-text {
    padding-left: 0.125rem !important;
    padding-right: 0.125rem !important;
    font-size: 0.72rem !important;
    min-height: unset !important;
    justify-content: center !important;
    text-align: center !important;
  }

  .pos-view-compact .fi-ta-content-grid .fi-ta-record .fi-ta-actions {
    padding-left: 0.125rem !important;
    padding-right: 0.125rem !important;
    justify-content: center !important;
  }

  .pos-view-compact .fi-ta-content-grid .fi-ta-record .fi-ta-actions > div {
    justify-content: center !important;
    width: 100% !important;
  }

  .pos-view-compact .fi-ta-content-grid .fi-ta-record .fi-ta-actions .mr-auto {
    margin-right: 0 !important;
  }

  /* Explicit Grid / Compact / List layouts for Alpine POS catalog */
  .pos-grid-large {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
  }
  @media (min-width: 768px) {
    .pos-grid-large {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (min-width: 1024px) {
    .pos-grid-large {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }

  .pos-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
  }
  @media (min-width: 768px) {
    .pos-grid-compact {
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }
  }
  @media (min-width: 1024px) {
    .pos-grid-compact {
      grid-template-columns: repeat(5, minmax(0, 1fr));
    }
  }
  @media (min-width: 1280px) {
    .pos-grid-compact {
      grid-template-columns: repeat(6, minmax(0, 1fr));
    }
  }

  .pos-grid-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .pos-img-large {
    aspect-ratio: 4 / 3;
    width: 100%;
    overflow: hidden;
  }
  .pos-img-compact {
    height: 76px;
    width: 100%;
    overflow: hidden;
  }
  .pos-img-list {
    height: 52px;
    width: 52px;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: 0.5rem;
  }

  .pos-card-list {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
  }
  .pos-card-grid {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
  }
  .pos-card-body-large {
    padding: 0.75rem;
    flex: 1 1 0%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.375rem;
  }
  .pos-card-body-compact {
    padding: 0.625rem;
    flex: 1 1 0%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.25rem;
  }
  .pos-card-body-list {
    flex: 1 1 0%;
    min-width: 0;
    padding: 0 0.25rem;
  }
</style>

<div class="pos-main-layout flex flex-col md:flex-row gap-3 lg:gap-4 items-start w-full">
  {{-- Kolom Katalog Produk (100% Frontend / Alpine.js) --}}
  <div class="pos-catalog-col w-full md:w-[56%] lg:w-[62%] xl:w-2/3 flex-1 min-w-0 space-y-3"
    :class="mobileTab === 'cart' ? 'hidden md:block' : 'block'">

    {{-- Category Filter Bar --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
      <button type="button" @click="selectedCategoryId = null"
        :class="selectedCategoryId === null ? 'bg-primary-600 text-white shadow-md shadow-primary-500/25 ring-2 ring-primary-500/30' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 border border-gray-200/80 dark:border-gray-700/80 shadow-sm'"
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs sm:text-sm font-semibold transition-all duration-150">
        <span>🌟 {{ __('Semua Menu') }}</span>
        <span
          :class="selectedCategoryId === null ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
          class="rounded-full px-1.5 py-0.5 text-xs font-bold"
          x-text="products.length">
        </span>
      </button>

      <template x-for="cat in categories" :key="cat.id">
        <button type="button" @click="selectedCategoryId = cat.id"
          :class="selectedCategoryId === cat.id ? 'bg-primary-600 text-white shadow-md shadow-primary-500/25 ring-2 ring-primary-500/30' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 border border-gray-200/80 dark:border-gray-700/80 shadow-sm'"
          class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs sm:text-sm font-semibold transition-all duration-150">
          <span x-text="categoryEmoji(cat.name) + ' ' + cat.name"></span>
          <span
            :class="selectedCategoryId === cat.id ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
            class="rounded-full px-1.5 py-0.5 text-xs font-bold"
            x-text="cat.products_count">
          </span>
        </button>
      </template>
    </div>

    {{-- Search Bar & View Mode Switcher --}}
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200/80 bg-white p-2.5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
      <div class="relative flex-1 min-w-[200px]">
        <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input type="text" x-model="searchQuery"
          placeholder="{{ __('Search (SKU, name, barcode)') }}"
          class="w-full rounded-lg border border-gray-200 bg-gray-50/70 py-2 pl-9 pr-8 text-xs sm:text-sm text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:bg-gray-900" />
        <button type="button" x-show="searchQuery.length > 0" @click="searchQuery = ''"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
          <x-heroicon-o-x-mark class="h-4 w-4" />
        </button>
      </div>

      <div class="flex items-center gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
        <button type="button" @click="viewMode = 'grid'" title="{{ __('Grid') }}"
          :class="viewMode === 'grid' ? 'bg-white text-primary-600 shadow-xs dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
          class="rounded-md p-1.5 transition">
          <x-heroicon-o-squares-2x2 class="h-4 w-4" />
        </button>
        <button type="button" @click="viewMode = 'compact'" title="{{ __('Compact') }}"
          :class="viewMode === 'compact' ? 'bg-white text-primary-600 shadow-xs dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
          class="rounded-md p-1.5 transition">
          <x-heroicon-o-view-columns class="h-4 w-4" />
        </button>
        <button type="button" @click="viewMode = 'list'" title="{{ __('List') }}"
          :class="viewMode === 'list' ? 'bg-white text-primary-600 shadow-xs dark:bg-gray-700 dark:text-primary-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
          class="rounded-md p-1.5 transition">
          <x-heroicon-o-bars-3 class="h-4 w-4" />
        </button>
      </div>
    </div>

    {{-- Product Grid / List / Compact --}}
    <div :class="{
        'pos-grid-large': viewMode === 'grid',
        'pos-grid-compact': viewMode === 'compact',
        'pos-grid-list': viewMode === 'list'
      }">
      <template x-for="product in filteredProducts" :key="product.id">
        <div @click="selectProduct(product)"
          :class="[
            getProductCartQty(product.id) > 0 ? 'ring-2 ring-primary-500 border-primary-500 bg-primary-50/30 dark:bg-primary-950/20' : 'border-gray-200/80 dark:border-gray-800 bg-white dark:bg-gray-900',
            viewMode === 'list' ? 'pos-card-list' : 'pos-card-grid'
          ]"
          class="rounded-xl border shadow-xs cursor-pointer select-none hover:shadow-md transition-all">
          {{-- Image --}}
          <div class="bg-gray-100 dark:bg-gray-800"
            :class="{
              'pos-img-list': viewMode === 'list',
              'pos-img-compact': viewMode === 'compact',
              'pos-img-large': viewMode === 'grid'
            }">
            <img :src="product.hero_image" :alt="product.name" loading="lazy"
              class="h-full w-full object-cover" />
          </div>

          {{-- Info --}}
          <div :class="{
              'pos-card-body-list': viewMode === 'list',
              'pos-card-body-compact': viewMode === 'compact',
              'pos-card-body-large': viewMode === 'grid'
            }">
            <div>
              <p class="font-extrabold text-primary-600 dark:text-primary-400"
                :class="viewMode === 'compact' ? 'text-[11px]' : 'text-xs'"
                x-text="formatMoney(product.selling_price)"></p>
              <p class="font-bold text-gray-900 dark:text-white leading-tight line-clamp-2 mt-0.5"
                :class="viewMode === 'compact' ? 'text-[11px]' : 'text-xs sm:text-sm'"
                x-text="product.name"></p>
              <template x-if="!product.is_non_stock">
                <p class="font-medium mt-0.5"
                  :class="[
                    viewMode === 'compact' ? 'text-[10px]' : 'text-[11px]',
                    product.stock < minimumStock ? 'text-danger-600 dark:text-danger-400' : 'text-emerald-600 dark:text-emerald-400'
                  ]"
                  x-text="'{{ __('Stock') }}: ' + product.stock"></p>
              </template>
            </div>

            {{-- Card Footer Actions (Grid / Compact) --}}
            <div x-show="viewMode !== 'list'" class="mt-1.5 flex items-center justify-between gap-1">
              <span
                :class="viewMode === 'compact' ? 'h-6 w-6' : 'h-7 w-7'"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 text-white shadow-xs">
                <x-heroicon-o-plus class="h-3.5 w-3.5" />
              </span>
              <span x-show="getProductCartQty(product.id) > 0"
                :class="viewMode === 'compact' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-1 text-xs'"
                class="inline-flex items-center gap-1 rounded-lg bg-primary-600 font-bold text-white shadow-xs">
                <x-heroicon-s-shopping-bag class="h-3 w-3" />
                <span x-text="getProductCartQty(product.id)"></span>
              </span>
            </div>
          </div>

          {{-- List View Actions --}}
          <div x-show="viewMode === 'list'" class="flex items-center gap-2 shrink-0">
            <span x-show="getProductCartQty(product.id) > 0"
              class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-bold text-white shadow-xs">
              <x-heroicon-s-shopping-bag class="h-3.5 w-3.5" />
              <span x-text="getProductCartQty(product.id)"></span>
            </span>
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white shadow-xs">
              <x-heroicon-o-plus class="h-4 w-4" />
            </span>
          </div>
        </div>
      </template>

      <div x-show="filteredProducts.length === 0"
        class="col-span-full flex h-44 flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-white/60 p-6 text-center dark:border-gray-800 dark:bg-gray-900/40">
        <x-heroicon-o-magnifying-glass class="h-8 w-8 text-gray-300 dark:text-gray-600 mb-2" />
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Menu tidak ditemukan') }}</p>
        <p class="text-xs text-gray-400 mt-0.5">{{ __('Coba kata kunci lain atau pilih kategori Semua Menu') }}</p>
      </div>
    </div>
  </div>

  {{-- Kolom Order Details / Keranjang --}}
  <div
    class="pos-cart-col w-full md:w-[44%] lg:w-[38%] xl:w-1/3 shrink-0 md:sticky md:top-2 self-start rounded-xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 md:max-h-[calc(100vh-1rem)] flex flex-col overflow-hidden"
    :class="mobileTab === 'products' ? 'hidden md:flex' : 'flex'">

    {{-- Header Sidebar (Sederhana & Ringkas) --}}
    <div class="p-3 sm:p-4 border-b border-gray-100 dark:border-gray-800 shrink-0">
      <div class="flex items-center justify-between gap-2" x-data="fullscreen">
        {{-- Sisi Kiri: Judul & Nama Kasir --}}
        <div class="flex items-center gap-2 min-w-0">
          <button type="button" @click="mobileTab = 'products'"
            class="md:hidden flex items-center justify-center rounded-lg bg-gray-100 p-1.5 text-gray-600 dark:bg-gray-800 dark:text-gray-300"
            title="{{ __('Kembali ke Menu') }}">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
          </button>
          <div class="min-w-0">
            <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white leading-tight">
              {{ __('Orders details') }}
            </h3>
            <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
              <span class="truncate font-medium">{{ pos_user()?->cashier_name ?? Filament::auth()->user()->cashier_name }}</span>
              @if ($activeDrawerId)
                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                  <span class="hidden sm:inline">{{ __('Shift Aktif') }}</span>
                </span>
              @endif
            </div>
          </div>
        </div>

        {{-- Sisi Kanan: Hanya Draft (jika ada) dan Menu Titik Tiga --}}
        <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
          {{-- Draft Pesanan: HANYA MUNCUL JIKA ADA DRAFT --}}
          <button type="button" x-show="drafts.length > 0" x-cloak
            x-on:click="$dispatch('open-modal', {id: 'draft-list-modal'})"
            title="{{ __('Lihat Draft Pesanan') }}"
            class="flex items-center gap-1 rounded-lg border border-lakasir-primary/40 bg-lakasir-primary/10 px-2.5 py-1.5 text-xs font-semibold text-lakasir-primary hover:bg-lakasir-primary hover:text-white transition">
            <x-heroicon-o-bookmark-square class="h-3.5 w-3.5" />
            <span class="hidden sm:inline">{{ __('Draft') }}</span>
            <span class="rounded-full bg-lakasir-primary px-1.5 text-[10px] font-bold text-white leading-tight"
              x-text="drafts.length">
            </span>
          </button>

          {{-- Dropdown Menu (Titik Tiga): Semua Aksi Disembunyikan di Sini --}}
          <x-filament::dropdown placement="bottom-end">
            <x-slot name="trigger">
              <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 transition" aria-label="Menu Opsi">
                <x-heroicon-o-ellipsis-vertical class="h-5 w-5" />
              </button>
            </x-slot>

            <x-filament::dropdown.list>
              <x-filament::dropdown.list.item wire:click="openAdminPinModal" icon="heroicon-o-shield-check">
                {{ __('Admin Panel') }}
              </x-filament::dropdown.list.item>

              <x-filament::dropdown.list.item wire:click="lockPos" icon="heroicon-o-user">
                {{ __('Kunci / Ganti Kasir') }}
              </x-filament::dropdown.list.item>

              <x-filament::dropdown.list.item x-on:click="$dispatch('open-modal', {id: 'qr-scanner-modal'})" icon="heroicon-o-qr-code">
                {{ __('Scan Barcode / Kamera') }}
              </x-filament::dropdown.list.item>

              <x-filament::dropdown.list.item x-on:mousedown="requestFullscreen" icon="heroicon-o-arrows-pointing-out">
                {{ __('Fullscreen') }}
              </x-filament::dropdown.list.item>

              <x-filament::dropdown.list.item x-on:mousedown="document.location.reload()" icon="heroicon-m-arrow-path">
                {{ __('Reload') }}
              </x-filament::dropdown.list.item>

              <template x-if="cart.length > 0">
                <x-filament::dropdown.list.item
                  x-on:click="if (confirm('{{ __('Yakin ingin mengosongkan keranjang?') }}')) { clearCart(); }"
                  icon="heroicon-o-trash"
                  color="danger">
                  {{ __('Kosongkan Keranjang') }}
                </x-filament::dropdown.list.item>
              </template>

              @if ($activeDrawerId)
                <x-filament::dropdown.list.item wire:click="openCloseShiftModal" icon="heroicon-o-lock-closed" color="danger">
                  {{ __('Tutup Kasir (Shift #' . $activeDrawerId . ')') }}
                </x-filament::dropdown.list.item>
              @endif
            </x-filament::dropdown.list>
          </x-filament::dropdown>
        </div>
      </div>
    </div>

    {{-- Body Scrollable Sidebar --}}
    <div class="p-3 sm:p-4 overflow-y-auto flex-1 min-h-0 space-y-3">
      <div x-show="activeDraftId" x-cloak
        class="flex items-center justify-between rounded-lg border border-lakasir-primary/40 bg-lakasir-primary/10 px-3 py-1.5 text-xs">
        <div class="flex items-center gap-1.5 font-semibold text-lakasir-primary truncate">
          <x-heroicon-m-bookmark class="h-4 w-4 shrink-0" />
          <span class="truncate">{{ __('Draft Aktif') }}: <span x-text="draftName"></span></span>
        </div>
        <button type="button" @click="clearCart()"
          class="text-xs font-semibold text-gray-500 hover:underline dark:text-gray-300 shrink-0 ml-2">
          {{ __('Tutup Draft') }}
        </button>
      </div>

      <div class="max-h-[30vh] sm:max-h-[35vh] space-y-2 overflow-y-auto pr-0.5">
        <template x-for="item in cart" :key="item.uid">
          <div class="mb-3 rounded-xl border border-gray-200/90 bg-white px-3.5 py-2.5 shadow-2xs dark:border-gray-800 dark:bg-gray-800/80">
            <div class="grid items-center space-x-3">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-gray-900 dark:text-white" x-text="item.name"></p>
                  <template x-if="item.selected_variations && item.selected_variations.length > 0">
                    <div class="mt-1 space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                      <template x-for="(v, vIdx) in item.selected_variations" :key="vIdx">
                        <div class="flex items-center gap-x-1">
                          <span class="font-medium text-gray-700 dark:text-gray-300" x-text="v.variation_name + ':'"></span>
                          <span x-text="v.option_name"></span>
                          <template x-if="v.price > 0">
                            <span class="font-semibold text-lakasir-primary" x-text="'(+' + formatMoney(v.price) + ')'"></span>
                          </template>
                        </div>
                      </template>
                    </div>
                  </template>
                </div>
                <p class="whitespace-nowrap font-semibold text-lakasir-primary" x-text="formatMoney(item.price)"></p>
              </div>
            </div>

            <template x-if="features.discount">
              <div class="grid grid-cols-2 items-center space-y-2 py-2 text-right">
                <div class="col-span-2">
                  <div class="mb-1 flex justify-end">
                    <x-filament::input.wrapper class="w-1/2">
                      <x-filament::input type="number" min="0"
                        x-model="item.discount_price"
                        @input="recalculateCartItem(item)"
                        placeholder="{{ __('Discount') }}" class="w-1/2 text-right" inputMode="numeric" />
                    </x-filament::input.wrapper>
                  </div>
                  <template x-if="item.discount_price && parseFloat(item.discount_price) > 0">
                    <p class="font-semibold text-lakasir-primary"
                      x-text="formatMoney(Math.max(0, item.price - parseFloat(item.discount_price || 0)))"></p>
                  </template>
                </div>
              </div>
            </template>

            <div class="flex h-8 items-center space-x-3 mt-1">
              <button type="button" class="rounded-lg !bg-gray-100 px-2 py-1" @click="reduceItem(item.uid)">
                <x-heroicon-o-minus-small class="h-4 w-4 !text-green-900" />
              </button>
              <x-filament::input.wrapper class="w-20">
                <x-filament::input type="number" min="1"
                  x-bind:value="item.qty"
                  @change="setItemQty(item.uid, $event.target.value)"
                  placeholder="{{ __('Qty') }}" class="w-1/2 text-right" inputMode="numeric" />
              </x-filament::input.wrapper>
              <button type="button" class="rounded-lg !bg-lakasir-primary px-2 py-1" @click="increaseItem(item.uid)">
                <x-heroicon-o-plus-small class="h-4 w-4 !text-white" />
              </button>
              <template x-if="item.price_units && item.price_units.length > 0">
                <button type="button" @click="openPriceSettingModal(item)"
                  class="rounded-lg border border-gray-200 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200">
                  <span x-text="item.price_unit_label || '{{ __('Satuan') }}'"></span>
                </button>
              </template>
              <button type="button" class="ml-auto rounded-lg !bg-danger-100 px-2 py-1" @click="deleteItem(item.uid)">
                <x-heroicon-o-trash class="h-4 w-4 !text-danger-900" />
              </button>
            </div>

            {{-- Catatan Per Item --}}
            <div class="mt-2" style="margin-top: 10px;">
              <x-filament::input.wrapper>
                <x-slot name="prefix">
                  <x-heroicon-o-pencil-square class="h-4 w-4 text-gray-400" />
                </x-slot>
                <x-filament::input type="text" x-model="item.note"
                  placeholder="{{ __('Catatan item (opsional)...') }}" class="text-xs" />
              </x-filament::input.wrapper>
            </div>

            {{-- Tombol Buka Tutup & Opsi Variasi --}}
            <template x-if="item.variations && item.variations.length > 0">
              <div class="mt-3 border-t pt-2 dark:border-gray-800">
                <div class="flex items-center justify-between">
                  <button type="button" @click="item.expanded = !item.expanded"
                    class="flex items-center gap-1.5 text-xs font-semibold text-lakasir-primary hover:opacity-80">
                    <span x-text="item.expanded ? '{{ __('Tutup Variasi') }}' : '{{ __('Atur / Ubah Variasi') }}'"></span>
                    <x-heroicon-m-chevron-down class="h-3.5 w-3.5 transition-transform duration-200"
                      ::class="item.expanded ? 'rotate-180' : ''" />
                  </button>

                  <template x-if="itemHasMissingRequired(item)">
                    <span class="rounded-full bg-danger-100 px-2 py-0.5 text-[10px] font-bold text-danger-700 dark:bg-danger-900/40 dark:text-danger-400">
                      {{ __('Wajib Dipilih') }}
                    </span>
                  </template>
                  <template x-if="!itemHasMissingRequired(item) && item.selected_variations && item.selected_variations.length > 0">
                    <span class="text-[11px] text-gray-500 dark:text-gray-400"
                      x-text="item.selected_variations.length + ' {{ __('terpilih') }}'">
                    </span>
                  </template>
                </div>

                <div x-show="item.expanded" x-collapse class="mt-2 space-y-2.5 rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                  <template x-for="variation in item.variations" :key="variation.id">
                    <div
                      :class="isVariationMissingOnItem(item, variation) ? 'border-danger-300 bg-danger-50/40 dark:border-danger-800 dark:bg-danger-950/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'"
                      class="space-y-1.5 rounded-md border p-2 transition-colors">
                      <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200" x-text="variation.name"></span>
                        <span x-show="variation.is_required"
                          :class="isVariationMissingOnItem(item, variation) ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400'"
                          class="text-[10px] font-bold">* {{ __('Wajib') }}</span>
                        <span x-show="!variation.is_required" class="text-[10px] text-gray-400">{{ __('Opsional') }}</span>
                      </div>

                      <div class="flex flex-wrap gap-1.5">
                        <template x-for="option in variation.options" :key="option.id">
                          <button type="button"
                            @click="toggleInlineVariationOption(item.uid, variation, option)"
                            :class="isOptionSelectedOnItem(item, variation.id, option.id) ? 'bg-lakasir-primary text-white font-semibold shadow-sm' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200'"
                            class="flex items-center gap-1 rounded-md px-2.5 py-1 text-xs transition-all">
                            <span x-text="option.name"></span>
                            <template x-if="option.price > 0">
                              <span class="text-[10px] opacity-80" x-text="'(+' + formatMoney(option.price) + ')'"></span>
                            </template>
                          </button>
                        </template>

                        <template x-if="!variation.is_required && variation.type === 'single' && hasSelectedForVariation(item, variation.id)">
                          <button type="button" @click="clearInlineVariation(item.uid, variation.id)"
                            class="rounded-md border border-gray-300 px-2 py-1 text-[11px] text-gray-500 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-400">
                            {{ __('Kosongkan') }}
                          </button>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>

                <div class="mt-2 pt-1" style="margin-top: 10px;">
                  <button type="button" @click="addSeparateVariationItem(item.product_id)"
                    class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-lakasir-primary/50 bg-lakasir-primary/5 px-4 py-2 text-xs font-semibold text-lakasir-primary transition hover:bg-lakasir-primary/15">
                    <x-heroicon-m-plus-circle class="h-4 w-4" />
                    <span>{{ __('Tambah Item Terpisah') }}</span>
                  </button>
                </div>
              </div>
            </template>
          </div>
        </template>

        <div x-show="cart.length === 0"
          class="flex h-36 flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50/60 p-4 text-center dark:border-gray-800 dark:bg-gray-800/40">
          <x-heroicon-o-shopping-bag class="h-8 w-8 mb-1.5 text-gray-300 dark:text-gray-600" />
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ __('Belum ada pesanan') }}</p>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ __('Pilih menu di sebelah kiri untuk menambahkan') }}</p>
        </div>
      </div>

      {{-- Pilihan Kategori Pesanan (Wajib) --}}
      <div>
        <div
          :class="!orderType ? 'border-amber-300 bg-amber-50/50 dark:border-amber-600/60 dark:bg-amber-950/20' : 'border-gray-200/90 bg-white dark:border-gray-700/80 dark:bg-gray-800/60'"
          class="w-full rounded-xl border px-3.5 py-2.5 transition-colors shadow-2xs">
          <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Kategori Pesanan') }}</span>
            <span
              :class="!orderType ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'"
              class="text-[10px] font-bold">
              * {{ __('Wajib Dipilih') }}
            </span>
          </div>
          <div class="grid grid-cols-3 gap-1.5">
            <button type="button" @click="orderType = 'dine_in'"
              :class="orderType === 'dine_in' ? 'border-lakasir-primary bg-lakasir-primary text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
              class="flex items-center justify-center gap-1 rounded-lg border px-1.5 py-1.5 text-[11px] sm:text-xs font-semibold transition">
              <span class="truncate">Dine In</span>
            </button>
            <button type="button" @click="orderType = 'take_away'"
              :class="orderType === 'take_away' ? 'border-lakasir-primary bg-lakasir-primary text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
              class="flex items-center justify-center gap-1 rounded-lg border px-1.5 py-1.5 text-[11px] sm:text-xs font-semibold transition">
              <span class="truncate">Take Away</span>
            </button>
            <button type="button" @click="orderType = 'ojek_online'"
              :class="orderType === 'ojek_online' ? 'border-lakasir-primary bg-lakasir-primary text-white shadow-xs' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
              class="flex items-center justify-center gap-1 rounded-lg border px-1.5 py-1.5 text-[11px] sm:text-xs font-semibold transition">
              <span class="truncate">Ojek Online</span>
            </button>
          </div>
        </div>
      </div>

      {{-- Detail Member, Meja, Voucher, Diskon (100% Frontend) --}}
      <div>
        <div
          class="w-full rounded-xl border border-gray-200/90 bg-white px-3.5 py-2.5 text-xs text-gray-700 shadow-2xs dark:border-gray-700/80 dark:bg-gray-800/60 dark:text-gray-200 space-y-2.5">
          <template x-if="features.member">
            <div class="flex items-center justify-between hover:underline cursor-pointer py-0.5"
              @click="$dispatch('open-modal', { id: 'modal-selected-member' })">
              <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Member') }}</p>
              <p class="font-bold text-gray-800 dark:text-gray-100" x-text="memberLabel || '{{ __('No Member Selected') }}'"></p>
            </div>
          </template>
          <template x-if="features.fnb">
            <div class="flex items-center justify-between hover:underline cursor-pointer py-0.5"
              @click="$dispatch('open-modal', { id: 'modal-selected-table' })">
              <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Table') }}</p>
              <p class="font-bold text-gray-800 dark:text-gray-100" x-text="tableLabel || '-'"></p>
            </div>
          </template>
          <template x-if="features.voucher">
            <div class="flex items-start justify-between gap-2 py-0.5">
              <div>
                <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Voucher') }}</p>
                <div class="flex flex-wrap gap-1.5 mt-1.5" x-show="!voucherCode && eligibleVouchers.length > 0">
                  <template x-for="v in eligibleVouchers" :key="v.id">
                    <button type="button" @click="assignVoucher(v.code)"
                      class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-100 px-2 py-1 text-[11px] font-semibold text-gray-700 hover:bg-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                      <x-heroicon-s-ticket class="h-3.5 w-3.5 text-lakasir-primary" />
                      <span x-text="v.code"></span>
                      <span class="rounded bg-lakasir-primary/15 px-1 py-0.2 text-[10px] font-bold text-lakasir-primary"
                        x-text="voucherBadgeLabel(v)"></span>
                    </button>
                  </template>
                </div>
              </div>
              <div class="flex items-center gap-x-2 font-bold text-gray-800 dark:text-gray-100 text-right">
                <template x-if="voucherCode">
                  <div class="flex flex-col items-end">
                    <span x-text="voucherCode + (activeVoucher ? ' (' + voucherBadgeLabel(activeVoucher) + ')' : '')"></span>
                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                      x-text="'-' + formatMoney(voucherDiscount)"></span>
                  </div>
                </template>
                <template x-if="!voucherCode">
                  <span>-</span>
                </template>
                <button type="button" x-show="voucherCode" @click="removeVoucher()"
                  class="p-1 bg-red-100 hover:bg-red-200 text-red-600 rounded-md flex items-center transition">
                  <x-heroicon-o-x-mark class="h-3.5 w-3.5 text-red-600" />
                </button>
              </div>
            </div>
          </template>
          <template x-if="features.discount">
            <div class="flex items-center justify-between hover:underline cursor-pointer py-0.5"
              @click="$dispatch('open-modal', { id: 'modal-manual-discount' })">
              <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Discount') }}</p>
              <p class="font-bold text-gray-800 dark:text-gray-100"
                x-text="manualDiscount > 0 ? formatMoney(manualDiscount) : '-'"></p>
            </div>
          </template>
        </div>
      </div>

      {{-- Total Ringkasan (100% Frontend) --}}
      <div>
        <div
          class="w-full rounded-xl border border-gray-200/90 bg-white px-3.5 py-2.5 text-xs text-gray-700 shadow-2xs dark:border-gray-700/80 dark:bg-gray-800/60 dark:text-gray-200 space-y-2">
          <div class="flex items-center justify-between py-0.5">
            <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Sub total') }}</p>
            <p class="font-bold text-lakasir-primary" x-text="formatMoney(subTotal)"></p>
          </div>
          <template x-if="features.selling_tax">
            <div class="flex items-center justify-between py-0.5">
              <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Tax') }}</p>
              <p class="font-bold text-lakasir-primary" x-text="tax + '%'"></p>
            </div>
          </template>
          <template x-if="voucherCode && voucherDiscount > 0">
            <div class="flex items-center justify-between py-0.5">
              <p class="text-gray-600 dark:text-gray-300 font-medium"
                x-text="'{{ __('Voucher') }} (' + voucherCode + (activeVoucher ? ' • ' + voucherBadgeLabel(activeVoucher) : '') + ')'"></p>
              <p class="font-bold text-emerald-600 dark:text-emerald-400" x-text="'-' + formatMoney(voucherDiscount)"></p>
            </div>
          </template>
          <template x-if="features.discount || totalDiscount > 0">
            <div class="flex items-center justify-between py-0.5">
              <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Discount price') }}</p>
              <p class="font-bold text-lakasir-primary" x-text="'(' + formatMoney(totalDiscount) + ')'"></p>
            </div>
          </template>
          <hr class="border-gray-200 dark:border-gray-700 my-1" />
          <div class="flex items-center justify-between py-0.5">
            <p class="font-bold text-gray-900 dark:text-white text-sm">{{ __('Total') }}</p>
            <p class="font-bold text-lakasir-primary text-base" x-text="formatMoney(totalPrice)"></p>
          </div>
        </div>
      </div>
    </div>

    {{-- Footer / Checkout Actions Sidebar --}}
    <div
      class="p-3 sm:p-4 pr-16 md:pr-16 lg:pr-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/80 dark:bg-gray-800/50 shrink-0">
      <div class="grid grid-cols-3 gap-2">
        <div class="col-span-1 flex rounded-lg border border-lakasir-primary bg-white dark:bg-gray-800 overflow-hidden shadow-sm">
          <button type="button" @click="quickSaveDraft()" :disabled="isSubmitting"
            title="{{ __('Simpan draf untuk nanti dan cetak ke dapur') }}"
            class="flex-1 flex items-center justify-center gap-1 px-1.5 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-lakasir-primary transition hover:bg-lakasir-primary/10 disabled:opacity-50">
            <x-heroicon-o-bookmark class="h-4 w-4 shrink-0" />
            <span class="truncate" x-text="activeDraftId ? '{{ __('Update') }}' : '{{ __('Draft') }}'"></span>
          </button>
          <button type="button" @click="openSaveDraftModal()" title="{{ __('Kustomisasi Keterangan Draft') }}"
            class="px-1 border-l border-lakasir-primary/20 text-lakasir-primary transition hover:bg-lakasir-primary/10 flex items-center justify-center">
            <x-heroicon-m-ellipsis-vertical class="h-4 w-4 shrink-0" />
          </button>
        </div>

        <button type="button" x-show="hasMissingRequiredVariations" x-cloak @click="notifyMissingVariations()"
          class="col-span-2 flex w-full items-center justify-center gap-1.5 rounded-lg bg-gray-400 px-2 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-white cursor-not-allowed">
          <x-heroicon-m-exclamation-triangle class="h-4 w-4 text-amber-300 shrink-0" />
          <span class="truncate">{{ __('Pilih Variasi Wajib') }}</span>
        </button>

        <button type="button" x-show="!hasMissingRequiredVariations && hasMissingOrderType" x-cloak @click="notifyMissingOrderType()"
          class="col-span-2 flex w-full items-center justify-center gap-1.5 rounded-lg bg-gray-400 px-2 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-white cursor-not-allowed">
          <x-heroicon-m-exclamation-triangle class="h-4 w-4 text-amber-300 shrink-0" />
          <span class="truncate">{{ __('Pilih Kategori Pesanan') }}</span>
        </button>

        <button type="button" x-show="!hasMissingRequiredVariations && !hasMissingOrderType"
          @click="saveAndProceedToPayment()" :disabled="isSubmitting"
          class="col-span-2 w-full rounded-lg bg-lakasir-primary px-2 py-2.5 sm:py-3 text-xs sm:text-sm font-semibold text-white transition hover:opacity-90 flex items-center justify-center gap-1.5 shadow-sm disabled:opacity-60">
          <span x-show="isSubmitting" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          <span class="truncate">{{ __('Simpan dan Bayar') }}</span>
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Sticky Floating Mobile Cart Bar (< 768px) --}}
<div class="fixed bottom-3 left-3 right-3 z-30 md:hidden"
  x-show="mobileTab === 'products' && cart.length > 0" x-cloak>
  <button type="button" @click="mobileTab = 'cart'"
    class="w-full flex items-center justify-between bg-lakasir-primary text-white rounded-xl px-4 py-3 shadow-lg hover:opacity-95 transition">
    <div class="flex items-center gap-2">
      <x-heroicon-o-shopping-bag class="h-5 w-5" />
      <span class="font-bold text-sm" x-text="totalCartQty + ' {{ __('item') }}'"></span>
      <span class="text-xs opacity-90" x-text="'(' + formatMoney(totalPrice) + ')'"></span>
    </div>
    <span class="text-xs font-bold uppercase tracking-wider flex items-center gap-1">
      {{ __('Lihat Pesanan') }} &rarr;
    </span>
  </button>
</div>
  {{-- modal --}}
  {{-- Modal Pilih Member (100% Frontend) --}}
  <x-filament::modal id="modal-selected-member" width="lg">
    <x-slot name="heading">{{ __('Search member') }}</x-slot>
    <div class="space-y-3">
      <x-filament::input.wrapper>
        <x-slot name="prefix">
          <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
        </x-slot>
        <x-filament::input type="text" x-model="memberSearch"
          placeholder="{{ __('Cari nama, email, atau kode member...') }}" />
      </x-filament::input.wrapper>

      <div class="max-h-64 overflow-y-auto space-y-1.5">
        <button type="button" @click="clearMember(); $dispatch('close-modal', {id: 'modal-selected-member'})"
          :class="!memberId ? 'border-lakasir-primary bg-lakasir-primary/10 text-lakasir-primary font-semibold' : 'border-gray-200 dark:border-gray-700'"
          class="w-full rounded-lg border px-3 py-2 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-gray-800">
          {{ __('No Member Selected') }}
        </button>
        <template x-for="m in filteredMembers" :key="m.id">
          <button type="button" @click="selectMember(m); $dispatch('close-modal', {id: 'modal-selected-member'})"
            :class="memberId == m.id ? 'border-lakasir-primary bg-lakasir-primary/10 text-lakasir-primary font-semibold' : 'border-gray-200 dark:border-gray-700'"
            class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-gray-800">
            <span x-text="m.name"></span>
            <span class="text-xs text-gray-400" x-text="m.code || m.email || ''"></span>
          </button>
        </template>
      </div>
    </div>
    <x-slot name="footer">
      <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'modal-selected-member'})">
        {{ __('Close') }}
      </x-filament::button>
    </x-slot>
  </x-filament::modal>

  {{-- Modal Manual Discount (100% Frontend) --}}
  <x-filament::modal id="modal-manual-discount" width="md">
    <x-slot name="heading">{{ __('Manual Discount') }}</x-slot>
    <div class="space-y-3">
      <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Discount') }}</label>
      <x-filament::input.wrapper>
        <x-slot name="prefix">{{ $currency }}</x-slot>
        <x-filament::input type="number" min="0" x-model="manualDiscountInput"
          placeholder="0" />
      </x-filament::input.wrapper>
    </div>
    <x-slot name="footer">
      <div class="flex justify-end gap-2">
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'modal-manual-discount'})">
          {{ __('Close') }}
        </x-filament::button>
        <x-filament::button x-on:click="applyManualDiscount(); $dispatch('close-modal', {id: 'modal-manual-discount'})">
          {{ __('Save') }}
        </x-filament::button>
      </div>
    </x-slot>
  </x-filament::modal>

  {{-- Modal Satuan Harga (100% Frontend) --}}
  <x-filament::modal id="modal-price-setting" width="md">
    <x-slot name="heading">{{ __('Pilih Satuan Harga') }}</x-slot>
    <div class="space-y-2" x-show="priceSettingItem">
      <button type="button" @click="applyPriceUnit(null)"
        :class="priceSettingItem && !priceSettingItem.price_unit_id ? 'border-lakasir-primary bg-lakasir-primary/10 text-lakasir-primary font-semibold' : 'border-gray-200 dark:border-gray-700'"
        class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-sm">
        <span>{{ __('Harga Normal') }}</span>
        <span x-text="priceSettingItem ? formatMoney(priceSettingItem.base_unit_price) : ''"></span>
      </button>
      <template x-if="priceSettingItem && priceSettingItem.price_units">
        <template x-for="pu in priceSettingItem.price_units" :key="pu.id">
          <button type="button" @click="applyPriceUnit(pu)"
            :class="priceSettingItem && priceSettingItem.price_unit_id == pu.id ? 'border-lakasir-primary bg-lakasir-primary/10 text-lakasir-primary font-semibold' : 'border-gray-200 dark:border-gray-700'"
            class="flex w-full items-center justify-between rounded-lg border px-3 py-2 text-sm">
            <span x-text="pu.unit"></span>
            <span x-text="formatMoney(pu.selling_price)"></span>
          </button>
        </template>
      </template>
    </div>
    <x-slot name="footer">
      <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'modal-price-setting'})">
        {{ __('Close') }}
      </x-filament::button>
    </x-slot>
  </x-filament::modal>

  <x-filament::modal id="proceed-the-payment" width="5xl">
    <form @submit.prevent="submitPayment()">
      <div class="my-2 grid gap-x-4 md:grid-cols-2">
        <div
          x-on:open-modal.window="if ($event.detail?.id === 'proceed-the-payment') { setTimeout(() => { if (document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { document.activeElement.blur(); } }, 60); }">
          <div class="rounded-lg">
            <div class="mb-4 grid grid-cols-4 gap-1">
              <template x-for="pm in paymentMethods" :key="pm.id">
                <div
                  @click="paymentMethodId = pm.id"
                  class="flex cursor-pointer justify-center rounded-md border-none px-4 py-2 text-sm hover:scale-105 dark:text-white"
                  :class="paymentMethodId == pm.id ? 'bg-lakasir-primary text-white' : 'dark:bg-gray-900 bg-gray-300'"
                  x-text="pm.name.substring(0, 8)">
                </div>
              </template>
            </div>
            <x-filament::input.wrapper
              x-show="paymentMethods.filter((pm) => pm.is_credit).some((pm) => pm.id == paymentMethodId)"
              class="mb-2">
              <x-slot name="prefix">
                {{ __('Due date') }}
              </x-slot>
              <x-filament::input type="date" x-model="dueDate" />
            </x-filament::input.wrapper>

            <div class="mb-4 rounded-xl border border-gray-200/90 bg-white px-3.5 py-2.5 text-xs text-gray-700 shadow-2xs dark:border-gray-700/80 dark:bg-gray-800/60 dark:text-gray-200 space-y-2">
              <div class="flex items-center justify-between py-0.5">
                <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Sub total') }}</p>
                <p class="font-bold text-lakasir-primary" x-text="formatMoney(subTotal)"></p>
              </div>
              <template x-if="features.selling_tax">
                <div class="flex items-center justify-between py-0.5">
                  <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Tax') }}</p>
                  <p class="font-bold text-lakasir-primary" x-text="tax + '%'"></p>
                </div>
              </template>
              <template x-if="voucherCode && voucherDiscount > 0">
                <div class="flex items-center justify-between py-0.5">
                  <p class="text-gray-600 dark:text-gray-300 font-medium"
                    x-text="'{{ __('Voucher') }} (' + voucherCode + (activeVoucher ? ' • ' + voucherBadgeLabel(activeVoucher) : '') + ')'"></p>
                  <p class="font-bold text-emerald-600 dark:text-emerald-400" x-text="'-' + formatMoney(voucherDiscount)"></p>
                </div>
              </template>
              <template x-if="features.discount || totalDiscount > 0">
                <div class="flex items-center justify-between py-0.5">
                  <p class="text-gray-600 dark:text-gray-300 font-medium">{{ __('Discount price') }}</p>
                  <p class="font-bold text-lakasir-primary" x-text="'(' + formatMoney(totalDiscount) + ')'"></p>
                </div>
              </template>
              <hr class="border-gray-200 dark:border-gray-700 my-1" />
              <div class="flex items-center justify-between py-0.5">
                <p class="font-bold text-gray-900 dark:text-white text-sm">{{ __('Total') }}</p>
                <p class="font-bold text-lakasir-primary text-base" x-text="formatMoney(totalPrice)"></p>
              </div>
              <div class="flex items-center justify-between py-0.5">
                <p class="font-bold text-gray-900 dark:text-white text-sm">{{ __('Money changes') }}</p>
                <p class="font-bold text-lakasir-primary text-base" x-text="formatMoney(moneyChanges)"></p>
              </div>
            </div>

            <div class="mb-3">
              <label class="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">
                {{ __('Note Penjualan (Opsional)') }}
              </label>
              <x-filament::input.wrapper>
                <x-slot name="prefix">
                  <x-heroicon-o-document-text class="h-4 w-4 text-gray-400" />
                </x-slot>
                <x-filament::input type="text" x-model="orderNote"
                  placeholder="{{ __('Isi catatan transaksi / penjualan...') }}" class="text-sm" />
              </x-filament::input.wrapper>
            </div>

            <template x-if="paymentError">
              <p class="mb-2 text-xs font-semibold text-danger-500" x-text="paymentError"></p>
            </template>

            <input id="display"
              class="w-full rounded-md border border-gray-300 bg-white p-2 text-right text-lg text-black dark:bg-gray-900 dark:text-white cursor-default select-none"
              readonly inputmode="none" :value="displayPayedMoney">
            <div class="mt-4 grid grid-cols-3 gap-4" id="calculator-button-shortcut">
            </div>
            <div class="mt-2 grid grid-cols-3 gap-2 lg:mt-2 lg:gap-2" id="calculator-button">
              <button type="button" class="col-span-3 rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend('no_changes')">{{ __('No change') }}</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(7)">7</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(8)">8</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(9)">9</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(4)">4</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(5)">5</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(6)">6</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(1)">1</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(2)">2</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(3)">3</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend('.')">.</button>
              <button type="button" class="rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend(0)">0</button>
              <button type="button"
                class="flex items-center justify-center rounded-md bg-gray-300 p-2 text-lg hover:bg-gray-400"
                @click="calcAppend('backspace')">
                <x-filament::icon icon="heroicon-o-backspace" class="h-5 w-5 text-gray-500 dark:text-white" />
              </button>
              <div class="col-span-3 flex gap-x-2">
                <button :disabled="isSubmitting" type="submit"
                  class="flex w-full items-center justify-center gap-x-2 rounded-md bg-lakasir-primary p-2 text-lg text-white hover:bg-[#ff6611] disabled:opacity-60">
                  <span x-show="isSubmitting" class="inline-block h-5 w-5 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                  <span>{{ __('Pay it') }}</span>
                </button>
                <button @click="$dispatch('close-modal', {id: 'proceed-the-payment'})" type="button"
                  class="flex w-full items-center justify-center gap-x-2 rounded-md bg-gray-300 p-2 text-lg">
                  {{ __('Close') }}
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="hidden max-h-[80vh] overflow-y-scroll md:block space-y-2">
          <template x-for="item in cart" :key="item.uid">
            <div class="flex justify-between rounded-lg border bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
              <div class="flex items-center space-x-3">
                <div class="space-y-1">
                  <p class="font-semibold" x-text="item.name"></p>
                  <template x-if="item.selected_variations && item.selected_variations.length > 0">
                    <div class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                      <template x-for="(v, idx) in item.selected_variations" :key="idx">
                        <div>
                          <span x-text="'- ' + v.variation_name + ': ' + v.option_name"></span>
                          <template x-if="v.price > 0">
                            <span class="font-medium text-lakasir-primary" x-text="' (+' + formatMoney(v.price) + ')'"></span>
                          </template>
                        </div>
                      </template>
                    </div>
                  </template>
                  <template x-if="item.note">
                    <div class="text-xs italic text-gray-500 dark:text-gray-400" x-text="'{{ __('Note') }}: ' + item.note"></div>
                  </template>
                  <div class="flex space-x-3 text-xs text-gray-400">
                    <p x-text="formatMoney(item.qty > 0 ? item.price / item.qty : item.price) + ' x ' + item.qty"></p>
                  </div>
                </div>
              </div>
              <div class="flex items-center">
                <p class="font-semibold text-lakasir-primary" x-text="formatMoney(item.price)"></p>
              </div>
            </div>
          </template>
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
      <div class="grid grid-cols-3 gap-x-2">
        <x-filament::button icon="heroicon-m-printer" id="printReceiptButton">
          {{ __('Print') }}
        </x-filament::button>
        <x-filament::button icon="heroicon-m-document-arrow-down" color="info" id="pdfReceiptButton">
          {{ __('Struk (PDF)') }}
        </x-filament::button>
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'success-modal'})">
          {{ __('Close') }}
        </x-filament::button>
      </div>
    </x-slot>
  </x-filament::modal>
  <x-filament::modal id="modal-selected-table" width="xl" :close-by-clicking-away="true" :close-by-escaping="true">
    <x-slot name="heading">{{ __('Choose the table') }}</x-slot>
    <div class="grid grid-cols-4 gap-4">
      <div @click="clearTable(); $dispatch('close-modal', {id: 'modal-selected-table'})"
        class="flex cursor-pointer justify-center rounded-md border border-gray-300 px-4 py-2 text-sm hover:scale-105 dark:text-white"
        :class="!tableId ? 'bg-gray-200 dark:bg-gray-700 font-bold' : 'dark:bg-gray-900'">
        {{ __('Tanpa Meja') }}
      </div>
      <template x-for="table in tables" :key="table.id">
        <div @click="selectTable(table); $dispatch('close-modal', {id: 'modal-selected-table'})"
          class="flex cursor-pointer justify-center rounded-md border border-lakasir-primary px-4 py-2 text-sm hover:scale-105 dark:text-white"
          :class="tableId == table.id ? 'bg-lakasir-primary text-white font-bold' : 'dark:bg-gray-900'"
          x-text="table.number">
        </div>
      </template>
    </div>
    <x-slot name="footer">
      <div class="flex justify-end">
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'modal-selected-table'})">
          {{ __('Close') }}
        </x-filament::button>
      </div>
    </x-slot>
  </x-filament::modal>

  <x-filament::modal id="variation-modal" width="xl" :close-by-clicking-away="false" :close-by-escaping="true">
    <x-slot name="header">
      <template x-if="variationModalProduct">
        <div class="flex items-center justify-between pr-6">
          <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white" x-text="variationModalProduct.name"></h2>
            <p class="text-sm font-semibold text-lakasir-primary">
              <span x-text="formatMoney(variationModalProduct.selling_price)"></span>
              <template x-if="variationModalProduct.unit">
                <span class="text-xs font-normal text-gray-400" x-text="'/ ' + variationModalProduct.unit"></span>
              </template>
            </p>
          </div>
          <template x-if="variationModalProduct.hero_image">
            <img :src="variationModalProduct.hero_image"
              class="h-14 w-14 rounded-lg border object-cover dark:border-gray-700"
              :alt="variationModalProduct.name">
          </template>
        </div>
      </template>
    </x-slot>

    <template x-if="variationModalProduct">
      <div class="max-h-[60vh] space-y-5 overflow-y-auto px-1 py-2">
        <template x-for="variation in variationModalProduct.variations" :key="variation.id">
          <div class="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/40">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-base font-bold text-gray-900 dark:text-white" x-text="variation.name"></span>
                <span x-show="variation.is_required"
                  class="rounded-full bg-danger-100 px-2 py-0.5 text-xs font-semibold text-danger-700 dark:bg-danger-900/40 dark:text-danger-400">
                  {{ __('Wajib') }}
                </span>
                <span x-show="!variation.is_required"
                  class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-normal text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                  {{ __('Opsional') }}
                </span>
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400"
                x-text="variation.type === 'multiple' ? '{{ __('Bisa pilih lebih dari satu') }}' : '{{ __('Pilih 1') }}'">
              </span>
            </div>

            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
              <template x-for="option in variation.options" :key="option.id">
                <button type="button"
                  @click="selectModalVariationOption(variation.id, option.id, variation.type)"
                  :class="isModalOptionSelected(variation.id, option.id, variation.type) ? 'border-lakasir-primary bg-lakasir-primary/10 font-semibold text-lakasir-primary ring-1 ring-lakasir-primary' : 'border-gray-200 bg-white text-gray-800 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200'"
                  class="flex items-center justify-between rounded-lg border p-3 text-left transition-all duration-150">
                  <div class="flex items-center gap-2">
                    <div
                      :class="[
                        variation.type === 'single' ? 'rounded-full' : 'rounded-md',
                        isModalOptionSelected(variation.id, option.id, variation.type) ? 'border-lakasir-primary bg-lakasir-primary text-white' : 'border-gray-400 bg-white dark:bg-gray-700'
                      ]"
                      class="flex h-4 w-4 items-center justify-center border">
                      <template x-if="isModalOptionSelected(variation.id, option.id, variation.type)">
                        <div class="h-1.5 w-1.5 rounded-full bg-white"></div>
                      </template>
                    </div>
                    <span class="text-sm" x-text="option.name"></span>
                  </div>
                  <span class="text-xs font-semibold"
                    :class="option.price > 0 ? 'text-lakasir-primary' : 'text-gray-400'"
                    x-text="option.price > 0 ? '+ ' + formatMoney(option.price) : 'Gratis'">
                  </span>
                </button>
              </template>
            </div>
          </div>
        </template>

        {{-- Jumlah Pesanan --}}
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900">
          <div>
            <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ __('Jumlah') }}</span>
            <p class="text-xs text-gray-500">{{ __('Tentukan jumlah porsi/item') }}</p>
          </div>
          <div class="flex items-center gap-3">
            <button type="button" @click="if (variationQty > 1) variationQty--"
              class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-200 font-bold text-gray-700 hover:bg-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
              <x-heroicon-o-minus class="h-4 w-4" />
            </button>
            <span class="w-8 text-center text-lg font-bold text-gray-900 dark:text-white" x-text="variationQty"></span>
            <button type="button" @click="variationQty++"
              class="flex h-8 w-8 items-center justify-center rounded-lg bg-lakasir-primary font-bold text-white hover:opacity-90">
              <x-heroicon-o-plus class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    </template>

    <x-slot name="footer">
      <div class="flex w-full items-center justify-between">
        <div class="text-left">
          <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Total Harga') }}</p>
          <p class="text-xl font-extrabold text-lakasir-primary" x-text="formatMoney(variationModalTotalPrice)"></p>
        </div>
        <div class="flex gap-2">
          <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'variation-modal'})">
            {{ __('Batal') }}
          </x-filament::button>
          <x-filament::button x-on:click="confirmVariationModal()" x-bind:disabled="!canSubmitVariationModal"
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

  {{-- Modal Simpan Draft Pesanan --}}
  <x-filament::modal id="save-draft-modal" width="md">
    <x-slot name="heading">
      <span x-text="activeDraftId ? '{{ __('Perbarui Draft Pesanan') }}' : '{{ __('Simpan Pesanan ke Draft') }}'"></span>
    </x-slot>

    <form @submit.prevent="submitCustomDraft()" class="space-y-4">
      <div class="space-y-2">
        <label class="text-sm font-semibold text-gray-700 dark:text-gray-200">
          {{ __('Nama Pelanggan / Keterangan Draft') }}
        </label>
        <x-filament::input.wrapper>
          <x-filament::input type="text" x-model="draftName"
            placeholder="{{ __('Contoh: Budi - Meja 4 / Pesanan GoFood') }}" />
        </x-filament::input.wrapper>
      </div>

      <div class="rounded-lg border bg-gray-50 p-3 text-xs space-y-1 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex justify-between">
          <span class="text-gray-500">{{ __('Kategori Pesanan') }}:</span>
          <span class="font-bold text-lakasir-primary" x-text="orderTypeLabel(orderType)"></span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">{{ __('Total Item') }}:</span>
          <span class="font-semibold" x-text="totalCartQty + ' {{ __('item') }}'"></span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">{{ __('Estimasi Total') }}:</span>
          <span class="font-bold text-lakasir-primary" x-text="formatMoney(totalPrice)"></span>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <x-filament::button color="gray" type="button" x-on:click="$dispatch('close-modal', {id: 'save-draft-modal'})">
          {{ __('Batal') }}
        </x-filament::button>
        <x-filament::button type="submit" icon="heroicon-o-bookmark" x-bind:disabled="isSubmitting">
          {{ __('Simpan Draft') }}
        </x-filament::button>
      </div>
    </form>
  </x-filament::modal>

  {{-- Modal Daftar Draft Pesanan --}}
  <x-filament::modal id="draft-list-modal" width="2xl">
    <x-slot name="heading">
      {{ __('Daftar Draft Pesanan Sementara') }}
    </x-slot>

    <div class="max-h-[65vh] space-y-3 overflow-y-auto py-1">
      <template x-if="drafts.length > 0">
        <div>
          <template x-for="draft in drafts" :key="draft.id">
            <div class="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-900" style="margin-bottom: 10px;">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="text-base font-bold text-gray-900 dark:text-white" x-text="draft.name"></p>
                    <span class="rounded-full bg-lakasir-primary/15 px-2.5 py-0.5 text-xs font-bold text-lakasir-primary"
                      x-text="draft.order_type_label">
                    </span>
                    <template x-if="draft.table_number">
                      <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        x-text="'{{ __('Meja') }}: ' + draft.table_number">
                      </span>
                    </template>
                    <span
                      :class="draft.is_kitchen_printed ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400'"
                      class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                      x-text="draft.is_kitchen_printed ? '{{ __('Sudah Cetak Dapur') }}' : '{{ __('Belum Cetak Dapur') }}'">
                    </span>
                  </div>
                  <p class="mt-0.5 text-xs text-gray-400">
                    <span x-text="draft.updated_at"></span>
                    <template x-if="draft.member_name">
                      <span x-text="' • {{ __('Member') }}: ' + draft.member_name"></span>
                    </template>
                  </p>
                </div>
                <p class="text-base font-bold text-lakasir-primary" x-text="formatMoney(draft.total_price)"></p>
              </div>

              <div class="mt-2 rounded-md bg-gray-50 p-2.5 text-xs text-gray-600 dark:bg-gray-800/60 dark:text-gray-300">
                <p class="font-semibold mb-1" x-text="draft.items.reduce((s, i) => s + parseFloat(i.qty || 0), 0) + ' {{ __('item pesanan') }}'"></p>
                <template x-for="(dItem, idx) in draft.items" :key="idx">
                  <div class="flex justify-between py-0.5">
                    <div>
                      <span class="font-medium" x-text="dItem.product_name || 'Item'"></span>
                      <span x-text="' (x' + (dItem.qty || 1) + ')'"></span>
                      <template x-if="dItem.selected_variations && dItem.selected_variations.length > 0">
                        <span class="text-gray-400"
                          x-text="' — ' + dItem.selected_variations.map(v => (v.variation_name || '') + ': ' + (v.option_name || '')).join(', ')">
                        </span>
                      </template>
                      <template x-if="dItem.note">
                        <span class="italic text-gray-400" x-text="' (' + dItem.note + ')'"></span>
                      </template>
                    </div>
                    <span x-text="formatMoney(dItem.price || 0)"></span>
                  </div>
                </template>
              </div>

              <div class="mt-3 flex items-center justify-end gap-2">
                <x-filament::button color="danger" size="sm" outlined icon="heroicon-o-trash"
                  x-on:click="deleteDraftFromList(draft.id)">
                  {{ __('Hapus') }}
                </x-filament::button>
                <x-filament::button color="gray" size="sm" icon="heroicon-o-printer"
                  x-on:click="printDraftFromList(draft.id)">
                  {{ __('Cetak Tiket') }}
                </x-filament::button>
                <x-filament::button size="sm" icon="heroicon-o-arrow-down-tray" x-on:click="loadDraftFromList(draft)">
                  {{ __('Muat & Bayar / Edit') }}
                </x-filament::button>
              </div>
            </div>
          </template>
        </div>
      </template>

      <template x-if="drafts.length === 0">
        <div class="flex h-36 flex-col items-center justify-center rounded-lg border border-dashed text-gray-400 dark:border-gray-800">
          <x-heroicon-o-bookmark-slash class="h-8 w-8 mb-1" />
          <p class="text-sm">{{ __('Belum ada draft pesanan yang disimpan.') }}</p>
        </div>
      </template>
    </div>

    <x-slot name="footer">
      <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'draft-list-modal'})">
        {{ __('Tutup') }}
      </x-filament::button>
    </x-slot>
  </x-filament::modal>

  {{-- Modal Tutup Kasir (Shift Closing Report / Z-Report) --}}
  <x-filament::modal id="close-shift-modal" width="3xl">
    <x-slot name="heading">
      <div class="flex items-center gap-2">
        <x-heroicon-o-document-chart-bar class="h-6 w-6 text-lakasir-primary" />
        <span>{{ __('Laporan Tutup Kasir (Shift Z-Report)') }}</span>
      </div>
    </x-slot>

    @if ($activeShiftSummary)
      <div class="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
        {{-- Metadata Shift --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs dark:border-gray-800 dark:bg-gray-900">
          <div>
            <p class="text-gray-400 text-[11px]">{{ __('Shift ID') }}</p>
            <p class="font-bold text-gray-800 dark:text-gray-200">#{{ $activeShiftSummary['drawer_id'] }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-[11px]">{{ __('Kasir Buka') }}</p>
            <p class="font-bold text-gray-800 dark:text-gray-200">{{ $activeShiftSummary['opened_by'] }}</p>
            <p class="text-[10px] text-gray-400">{{ $activeShiftSummary['opened_at'] }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-[11px]">{{ __('Kasir Tutup') }}</p>
            <p class="font-bold text-gray-800 dark:text-gray-200">{{ $activeShiftSummary['closed_by'] }}</p>
            <p class="text-[10px] text-gray-400">{{ $activeShiftSummary['closed_at'] }}</p>
          </div>
          <div>
            <p class="text-gray-400 text-[11px]">{{ __('Total Transaksi') }}</p>
            <p class="font-bold text-lakasir-primary text-sm">{{ $activeShiftSummary['total_transactions'] }} {{ __('Nota') }}</p>
          </div>
        </div>

        {{-- Ringkasan Penjualan & Kas Tunai --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
          <div class="rounded-xl border border-gray-200 bg-white p-3 text-xs dark:border-gray-800 dark:bg-gray-900">
            <span class="text-gray-500 dark:text-gray-400">{{ __('Total Penjualan Shift') }}</span>
            <p class="mt-1 text-base font-bold text-gray-800 dark:text-gray-200">{{ price_format($activeShiftSummary['total_grand_total']) }}</p>
          </div>
          <div class="rounded-xl border border-gray-200 bg-white p-3 text-xs dark:border-gray-800 dark:bg-gray-900">
            <span class="text-gray-500 dark:text-gray-400">{{ __('Total Item Terjual') }}</span>
            <p class="mt-1 text-base font-bold text-lakasir-primary">{{ $activeShiftSummary['total_qty_sold'] }} {{ __('Porsi/Pcs') }}</p>
          </div>
          <div class="rounded-xl border border-emerald-500/30 bg-emerald-50/60 p-3 text-xs dark:border-emerald-800 dark:bg-emerald-950/30">
            <span class="text-emerald-800 dark:text-emerald-300 font-semibold">{{ __('Total Kas Tunai (Uang di Laci)') }}</span>
            <p class="mt-1 text-lg font-extrabold text-emerald-700 dark:text-emerald-300">{{ price_format($activeShiftSummary['expected_cash_in_drawer']) }}</p>
            <p class="text-[10px] text-emerald-600/80 dark:text-emerald-400/80">{{ __('Total Uang Penjualan Tunai') }}</p>
          </div>
        </div>

        {{-- TABEL ITEM-ITEM YANG TERJUAL & JUMLAHNYA --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 overflow-hidden">
          <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/80 px-4 py-2.5 dark:border-gray-800 dark:bg-gray-800/60">
            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
              <x-heroicon-m-shopping-bag class="h-4 w-4 text-lakasir-primary" />
              <span>{{ __('Daftar Item yang Terjual') }}</span>
            </h4>
            <span class="rounded-full bg-lakasir-primary/15 px-2 py-0.5 text-xs font-bold text-lakasir-primary">
              {{ $activeShiftSummary['total_qty_sold'] }} {{ __('Porsi/Pcs Terjual') }}
            </span>
          </div>

          <div class="max-h-56 overflow-y-auto">
            @if (!empty($activeShiftSummary['sold_items']))
              <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-gray-100/90 dark:bg-gray-800/90 text-gray-600 dark:text-gray-300 text-[11px] font-semibold">
                  <tr>
                    <th class="px-3.5 py-2">{{ __('Nama Menu / Produk') }}</th>
                    <th class="px-3.5 py-2 text-center" style="width: 80px;">{{ __('Qty') }}</th>
                    <th class="px-3.5 py-2 text-right" style="width: 130px;">{{ __('Total Penjualan') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                  @foreach ($activeShiftSummary['sold_items'] as $sItem)
                    <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/40 transition">
                      <td class="px-3.5 py-2 font-medium text-gray-900 dark:text-white">{{ $sItem['name'] }}</td>
                      <td class="px-3.5 py-2 text-center font-bold text-lakasir-primary">{{ $sItem['qty'] }}</td>
                      <td class="px-3.5 py-2 text-right font-medium text-gray-700 dark:text-gray-300">{{ price_format($sItem['total']) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            @else
              <div class="py-8 text-center text-xs text-gray-400">
                <x-heroicon-o-shopping-bag class="h-8 w-8 mx-auto mb-1 opacity-40" />
                <p>{{ __('Belum ada item pesanan yang terjual pada shift ini.') }}</p>
              </div>
            @endif
          </div>
        </div>

        {{-- Ringkasan Pembayaran Non-Tunai & Omset --}}
        <div class="rounded-xl border border-gray-200 bg-white p-3.5 text-xs dark:border-gray-800 dark:bg-gray-900 space-y-2">
          <h4 class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider text-[11px] mb-1">
            {{ __('Ringkasan Metode Pembayaran') }}
          </h4>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            @foreach ($activeShiftSummary['payment_breakdown'] ?? [] as $pBreak)
              <div class="rounded-lg border border-gray-100 bg-gray-50/60 p-2 dark:border-gray-800 dark:bg-gray-800/40">
                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $pBreak['name'] }} ({{ $pBreak['count'] }}x)</p>
                <p class="font-bold text-gray-900 dark:text-white text-xs mt-0.5">{{ price_format($pBreak['total']) }}</p>
              </div>
            @endforeach
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800 text-sm">
            <span class="font-bold text-gray-700 dark:text-gray-300">{{ __('Total Omset Shift') }}:</span>
            <span class="text-base font-extrabold text-lakasir-primary">{{ price_format($activeShiftSummary['total_grand_total']) }}</span>
          </div>
        </div>
      </div>
    @endif

    <x-slot name="footer">
      <div class="flex items-center justify-between gap-2 w-full">
        <x-filament::button color="gray" x-on:click="$dispatch('close-modal', {id: 'close-shift-modal'})">
          {{ __('Batal') }}
        </x-filament::button>

        <div class="flex items-center gap-2">
          <x-filament::button color="warning" outlined icon="heroicon-o-printer" wire:click="printCurrentShift">
            {{ __('Cetak Saja (X-Report)') }}
          </x-filament::button>

          <x-filament::button color="danger" icon="heroicon-o-lock-closed" wire:click="submitCloseShift">
            {{ __('Tutup Shift & Cetak Laporan') }}
          </x-filament::button>
        </div>
      </div>
    </x-slot>
  </x-filament::modal>

  {{-- ======================================================== --}}
  {{-- POS LOCKSCREEN OVERLAY (Wajib Masukkan PIN Kasir)       --}}
  {{-- ======================================================== --}}
  @if ($isPosLocked)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background-color: rgba(15, 23, 42, 0.68); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
      <div class="w-full max-w-sm sm:max-w-md rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl p-6 sm:p-7 space-y-5">
        {{-- Store Branding --}}
        <div class="text-center space-y-1.5">
          @if ($about?->photo || $about?->logo_url)
            <img src="{{ $about?->logo_url ?? $about?->photo }}" alt="{{ $about?->shop_name ?? 'POS' }}" class="h-12 w-12 rounded-xl mx-auto object-cover shadow-sm">
          @else
            <div class="h-12 w-12 rounded-xl bg-primary-600 text-white flex items-center justify-center mx-auto text-lg font-bold shadow-sm">
              {{ strtoupper(substr($about?->shop_name ?? 'L', 0, 1)) }}
            </div>
          @endif
          <h2 class="text-lg font-bold text-gray-900 dark:text-white">
            {{ $about?->shop_name ?? config('app.name', 'Lakasir') }}
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Pilih akun kasir dan masukkan PIN untuk mulai melayani pesanan.') }}
          </p>
        </div>

        {{-- Error Alert --}}
        @if ($posPinError)
          <div class="rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 p-2.5 text-xs font-semibold text-danger-700 dark:text-danger-400 text-center animate-pulse">
            {{ $posPinError }}
          </div>
        @endif

        {{-- Cashier Selection --}}
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
            {{ __('Pilih Akun Kasir') }}
          </label>
          <select
            wire:model.live="selectedCashierId"
            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3.5 py-2.5 text-sm font-medium text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
          >
            <option value="">{{ __('-- Pilih Kasir --') }}</option>
            @foreach ($this->cashiers as $cUser)
              <option value="{{ $cUser->id }}">
                {{ $cUser->name }} ({{ $cUser->is_owner ? 'Owner' : ($cUser->roles->first()?->name ?? 'Kasir') }}) {{ $cUser->hasPin() ? '' : '⚠️ No PIN' }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- PIN Input --}}
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5 text-center">
            {{ __('PIN Kasir') }}
          </label>
          <input
            type="password"
            inputmode="numeric"
            autocomplete="one-time-code"
            wire:model="posPin"
            wire:keydown.enter="unlockPos"
            placeholder="••••"
            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 px-4 py-2.5 text-center text-xl tracking-[0.4em] font-bold text-gray-900 dark:text-white focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 focus:ring-1 focus:ring-primary-500"
          >
        </div>

        {{-- Unlock Action --}}
        <button
          type="button"
          wire:click="unlockPos"
          class="w-full rounded-xl bg-primary-600 hover:bg-primary-500 py-2.5 text-sm font-bold text-white shadow-md shadow-primary-500/20 active:scale-98 transition flex items-center justify-center gap-2"
        >
          <x-heroicon-o-lock-open class="h-4 w-4" />
          <span>{{ __('Buka POS (Masuk)') }}</span>
        </button>

        {{-- Bottom Links: Admin Panel & Logout --}}
        <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-800 pt-3 text-xs">
          <button
            type="button"
            wire:click="openAdminPinModal"
            class="inline-flex items-center gap-1.5 font-semibold text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition"
          >
            <x-heroicon-o-shield-check class="h-4 w-4 text-primary-600" />
            <span>{{ __('Admin Panel') }}</span>
          </button>

          <button
            type="button"
            wire:click="logoutDevice"
            wire:confirm="{{ __('Yakin ingin logout perangkat?') }}"
            class="inline-flex items-center gap-1 text-gray-500 hover:text-danger-600 dark:hover:text-danger-400 transition"
          >
            <x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4" />
            <span>{{ __('Logout') }}</span>
          </button>
        </div>
      </div>
    </div>
  @endif

  {{-- ======================================================== --}}
  {{-- POP-UP BUKA TOKO (Jika Toko Belum Dibuka / Beda Hari)   --}}
  {{-- ======================================================== --}}
  @if (! $isPosLocked && $showOpenStoreModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background-color: rgba(15, 23, 42, 0.68); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
      <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl p-6 text-center space-y-5">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400 shadow-sm">
          <x-heroicon-o-building-storefront class="h-8 w-8" />
        </div>

        <div class="space-y-1.5">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">
            {{ __('Toko Belum Dibuka') }}
          </h3>
          @if ($hasStaleOpenDrawer)
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
              {{ __('Sesi toko sebelumnya (:date) belum ditutup. Menekan tombol Buka Toko akan otomatis menutup & mencetak laporan tutup toko sebelumnya, lalu membuka toko untuk hari ini.', ['date' => $staleDrawerOpenedAt]) }}
            </p>
          @else
            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
              {{ __('Toko belum dibuka untuk hari ini. Silakan tekan tombol di bawah untuk membuka toko dan mencetak laporan buka toko.') }}
            </p>
          @endif
        </div>

        <button
          type="button"
          wire:click="openStoreFromModal"
          wire:loading.attr="disabled"
          class="w-full rounded-xl bg-primary-600 hover:bg-primary-500 py-3 px-4 text-sm font-bold text-white shadow-md shadow-primary-500/20 active:scale-98 transition flex items-center justify-center gap-2 disabled:opacity-60"
        >
          <span wire:loading wire:target="openStoreFromModal" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          <x-heroicon-o-lock-open wire:loading.remove wire:target="openStoreFromModal" class="h-4 w-4" />
          <span>{{ __('Buka Toko') }}</span>
        </button>
      </div>
    </div>
  @endif

  {{-- ======================================================== --}}
  {{-- ADMIN PIN MODAL (Verifikasi PIN untuk Masuk Admin Panel) --}}
  {{-- ======================================================== --}}
  @if ($showAdminModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
      style="background-color: rgba(15, 23, 42, 0.68); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
      <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow-sm">
              <x-heroicon-o-shield-check class="h-5 w-5" />
            </div>
            <div>
              <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                @if ($adminPinAction === 'open_shift')
                  {{ __('Otorisasi Buka Kasir') }}
                @elseif ($adminPinAction === 'close_shift')
                  {{ __('Otorisasi Tutup Kasir') }}
                @else
                  {{ __('Buka Admin Panel') }}
                @endif
              </h3>
              <p class="text-[11px] text-gray-500 dark:text-gray-400">
                @if ($adminPinAction === 'open_shift')
                  {{ __('Masukkan PIN Admin untuk membuka sesi shift kasir') }}
                @elseif ($adminPinAction === 'close_shift')
                  {{ __('Masukkan PIN Admin untuk menutup shift kasir & cetak Z-Report') }}
                @else
                  {{ __('Memerlukan PIN Akun Admin') }}
                @endif
              </p>
            </div>
          </div>
          <button
            type="button"
            wire:click="closeAdminPinModal"
            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600"
          >
            <x-heroicon-o-x-mark class="h-5 w-5" />
          </button>
        </div>

        @if ($adminPinError)
          <div class="rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 p-2.5 text-xs font-semibold text-danger-700 dark:text-danger-400 text-center animate-pulse">
            {{ $adminPinError }}
          </div>
        @endif

        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5 text-center">
            {{ __('PIN Admin') }}
          </label>
          <input
            type="password"
            inputmode="numeric"
            autocomplete="one-time-code"
            wire:model="adminPin"
            wire:keydown.enter="submitAdminPin"
            placeholder="••••"
            class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/80 px-4 py-2.5 text-center text-xl tracking-[0.4em] font-bold text-gray-900 dark:text-white focus:border-primary-500 focus:bg-white dark:focus:bg-gray-800 focus:ring-1 focus:ring-primary-500"
          >
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100 dark:border-gray-800">
          <button
            type="button"
            wire:click="closeAdminPinModal"
            class="rounded-xl border border-gray-200 dark:border-gray-700 px-3.5 py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
          >
            {{ __('Batal') }}
          </button>
          <button
            type="button"
            wire:click="submitAdminPin"
            class="rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-4 py-2 text-xs font-bold hover:opacity-90 transition shadow-sm"
          >
            @if ($adminPinAction === 'open_shift')
              {{ __('Verifikasi & Buka Kasir') }}
            @elseif ($adminPinAction === 'close_shift')
              {{ __('Verifikasi & Tutup Kasir') }}
            @else
              {{ __('Buka Admin Panel') }}
            @endif
          </button>
        </div>
      </div>
    </div>
  @endif

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
      const skipKitchenPrint = Boolean(event.skipKitchenPrint);
      $wire.dispatch('close-modal', {
        id: 'proceed-the-payment'
      });

      $wire.dispatch('open-modal', {
        id: 'success-modal',
        money_changes: selling.money_changes
      });
      setTimeout(() => {
        const changesEl = document.getElementById('changes');
        if (changesEl) changesEl.innerHTML = moneyFormat(selling.money_changes);
      }, 300);

      // PENCETAKAN OTOMATIS MULTI-PRINTER SAAT TRANSAKSI SELESAI
      setTimeout(() => {
        executeMultiPrinterPrint(selling, true, skipKitchenPrint);
      }, 500);
    });

    $wire.on('draft-saved', (event) => {
      if (event && event.draft) {
        setTimeout(() => {
          executeDraftPrint(event.draft);
        }, 300);
      }
    });

    let shiftPrintQueue = Promise.resolve();

    $wire.on('open-shift-printed', (event) => {
      if (event && event.slip) {
        shiftPrintQueue = shiftPrintQueue.then(() => new Promise((resolve) => {
          setTimeout(async () => {
            try {
              await executeOpenShiftPrint(event.slip);
            } finally {
              resolve();
            }
          }, 300);
        }));
      }
    });

    $wire.on('close-shift-printed', (event) => {
      if (event && event.report) {
        shiftPrintQueue = shiftPrintQueue.then(() => new Promise((resolve) => {
          setTimeout(async () => {
            try {
              await executeCloseShiftPrint(event.report);
            } finally {
              resolve();
            }
          }, 300);
        }));
      }
    });

    document.getElementById("pdfReceiptButton")?.addEventListener('click', () => {
      if (selling && selling.id) {
        const printerData = getPrinter();
        const paperWidth = (printerData && !(printerData instanceof Error) && printerData.paper_width) ? printerData.paper_width : '58mm';
        window.open('/member/sellings/' + selling.id + '/receipt-pdf?width=' + paperWidth, '_blank');
      }
    });

    async function executeMultiPrinterPrint(currentSelling, isAutomatic = false, skipKitchenPrint = false) {
      if (!currentSelling || !currentSelling.id) {
        return;
      }
      let about = @js($about);
      const dbPrinters = @js(\App\Models\Tenants\Printer::active()->orderBy('id')->get()->map(fn($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'printerId' => $p->device_id,
        'device_id' => $p->device_id,
        'device_name' => $p->device_name,
        'driver' => $p->connection_type,
        'paper_width' => $p->paper_width ?: '58mm',
        'print_all_categories' => (bool) $p->print_all_categories,
        'category_ids' => array_map('intval', $p->category_ids ?? []),
        'show_price' => (bool) $p->show_price,
        'print_on_draft' => (bool) ($p->print_on_draft ?? !$p->show_price),
        'header' => $p->header,
        'footer' => $p->footer,
        'copies' => (int) ($p->copies ?: 1),
      ])->values());

      const legacyPrinter = getPrinter();
      const printersList = (Array.isArray(dbPrinters) && dbPrinters.length > 0)
        ? dbPrinters
        : ((legacyPrinter && !(legacyPrinter instanceof Error) && (legacyPrinter.printerId || window.LakasirBridge)) ? [{
          name: legacyPrinter.name || 'Printer Kasir',
          printerId: legacyPrinter.printerId || 'kasir',
          device_id: legacyPrinter.printerId || 'kasir',
          driver: legacyPrinter.driver || 'usb',
          paper_width: legacyPrinter.paper_width || '58mm',
          print_all_categories: true,
          category_ids: [],
          show_price: true,
          header: legacyPrinter.header,
          footer: legacyPrinter.footer,
          copies: 1,
        }] : []);

      const defaultPaperWidth = (printersList[0] && printersList[0].paper_width) ? printersList[0].paper_width : '58mm';

      try {
        if (printersList.length === 0) {
          if (!isAutomatic) {
            new FilamentNotification()
              .title('@lang('Printer belum diatur atau tidak aktif')')
              .body('@lang('Struk otomatis disimpan dalam format PDF dan dibuka untuk dicetak.')')
              .warning()
              .send();

            window.open('/member/sellings/' + currentSelling.id + '/receipt-pdf?width=' + defaultPaperWidth, '_blank');
          }
          return;
        }

        // Jika pencetakan otomatis dan background print bridge sedang aktif:
        // Picu polling instan agar background service mencetak ke printer yang sesuai tanpa double-print.
        if (isAutomatic && window.LakasirBridge && typeof window.LakasirBridge.isServiceRunning === 'function' && window.LakasirBridge.isServiceRunning()) {
          console.log('Background Print Bridge aktif; memicu pencetakan otomatis via server queue...');
          if (typeof window.LakasirBridge.triggerPoll === 'function') {
            window.LakasirBridge.triggerPoll();
          }
          new FilamentNotification()
            .title('@lang('Pencetakan Otomatis Diproses')')
            .body('@lang('Struk kasir dan nota pesanan otomatis dicetak sesuai routing printer.')')
            .success()
            .send();
          return;
        }

        let anyPrinted = false;

        for (const printerData of printersList) {
          if (skipKitchenPrint && !printerData.show_price) {
            continue;
          }
          const allDetails = (currentSelling && Array.isArray(currentSelling.selling_details)) ? currentSelling.selling_details : [];
          const filteredDetails = printerData.print_all_categories
            ? allDetails
            : allDetails.filter(d => {
              const catId = d.product && d.product.category_id ? parseInt(d.product.category_id, 10) : null;
              return catId !== null && Array.isArray(printerData.category_ids) && printerData.category_ids.includes(catId);
            });

          // Lewati printer ini jika pesanan tidak mengandung menu dari kategori printer tersebut
          if (filteredDetails.length === 0) {
            continue;
          }

          const paperWidth = printerData.paper_width || '58mm';
          const dividerLine = paperWidth === '80mm'
            ? '------------------------------------------------'
            : '-------------------------------';

          const targetDeviceId = printerData.device_id || printerData.printerId || 'kasir';
          const numCopies = Math.max(1, parseInt(printerData.copies || 1));

          for (let copyIndex = 1; copyIndex <= numCopies; copyIndex++) {
            const isMerchantCopy = (copyIndex > 1);
            const shouldShowHeader = (printerData.show_header !== false && printerData.show_header !== 0) && !isMerchantCopy;

            const printer = new Printer(targetDeviceId, paperWidth);
            let printerAction = printer.font('a');

            if (printerData.show_price) {
              // FORMAT STRUK DENGAN HARGA (KASIR)
              if (isMerchantCopy) {
                printerAction.align('center')
                  .text('*** SALINAN TOKO / UNTUK DISIMPAN ***')
                  .align('left')
                  .text(dividerLine);
              }

              if (shouldShowHeader && about != undefined && about != null) {
                printerAction.size(1)
                  .align('center')
                  .text(about.shop_name || '')
                  .size(0);
                if (about.shop_location) {
                  printerAction.text(about.shop_location);
                }
                if (about.phone) {
                  printerAction.text('Telp: ' + about.phone);
                }
                if (about.email) {
                  printerAction.text('Email: ' + about.email);
                }
                if (about.website) {
                  printerAction.text(about.website);
                }
                if (about.npwp) {
                  printerAction.text('NPWP: ' + about.npwp);
                }
                if (printerData.header) {
                  printerAction.text(printerData.header);
                }
                printerAction.align('left').text(dividerLine);
              } else if (printerData.header && !isMerchantCopy) {
                printerAction.align('center').text(printerData.header).align('left').text(dividerLine);
              }
              printerAction.table(['Station', isMerchantCopy ? (printerData.name + ' (Salinan)') : (printerData.name || 'Kasir')]);
            printerAction.table(['@lang('Cashier')', currentSelling.user.name]);
            if (currentSelling.order_type_label && currentSelling.order_type_label !== '-') {
              printerAction.table(['@lang('Kategori')', currentSelling.order_type_label]);
            }
            if (currentSelling.table && currentSelling.table.number) {
              printerAction.table(['@lang('Table')', currentSelling.table.number]);
            }
            if (currentSelling.payment_method && currentSelling.payment_method.name) {
              printerAction.table(['@lang('Payment method')', currentSelling.payment_method.name]);
            }
            if (currentSelling.member && currentSelling.member.name) {
              printerAction.table(['Member', currentSelling.member.name]);
            }
            printerAction.text(dividerLine);

            filteredDetails.forEach(sellingDetail => {
              let price = sellingDetail.price;
              printerAction.table([
                sellingDetail.product.name,
                moneyFormat(sellingDetail.price / sellingDetail.qty) + ' x ' + sellingDetail.qty.toString()
              ]);
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
                printerAction.align('right').text(`(${moneyFormat(sellingDetail.discount_price)})`);
              }
              printerAction.align('right').text(moneyFormat(price)).align('left');
            });

            printerAction.text(dividerLine);
            if ("@js(feature(SellingTax::class))" == 'true') {
              printerAction.table(['@lang('Tax')', `${currentSelling.tax}%`])
                .table(['@lang('Tax price')', moneyFormat(currentSelling.tax_price)]);
            }
            printerAction.table(['@lang('Subtotal')', moneyFormat(currentSelling.total_price)]);
            if ("@js(feature(Discount::class))" == 'true') {
              printerAction.table([
                '@lang('Discount')',
                `(${moneyFormat(currentSelling.total_discount_per_item + currentSelling.discount_price)})`
              ]);
            }
            printerAction
              .table(['@lang('Total price')', moneyFormat(currentSelling.grand_total_price)])
              .text(dividerLine)
              .table(['@lang('Payed money')', moneyFormat(currentSelling.payed_money)])
              .table(['@lang('Change')', moneyFormat(currentSelling.money_changes)])
              .align('center');

            if (printerData.footer) {
              printerAction.text(printerData.footer);
            }
          } else {
            // FORMAT NOTA CHECKER TANPA HARGA (DAPUR / BAR)
            printerAction.size(1)
              .align('center')
              .text('*** ' + (printerData.name || 'CHECKER').toUpperCase() + ' ***')
              .size(0);
            if (printerData.header) {
              printerAction.text(printerData.header);
            }
            printerAction.align('left')
              .text(dividerLine)
              .table(['No. Pesanan', currentSelling.code || ('#' + currentSelling.id)])
              .table(['@lang('Cashier')', currentSelling.user.name]);
            if (currentSelling.order_type_label && currentSelling.order_type_label !== '-') {
              printerAction.table(['@lang('Kategori')', currentSelling.order_type_label]);
            }
            if (currentSelling.table && currentSelling.table.number) {
              printerAction.table(['@lang('Table')', currentSelling.table.number]);
            }
            printerAction.text(dividerLine);

            let totalQty = 0;
            filteredDetails.forEach(sellingDetail => {
              totalQty += Number(sellingDetail.qty || 1);
              printerAction.text(`${sellingDetail.qty}x  ${sellingDetail.product.name}`);
              if (sellingDetail.selected_variations && sellingDetail.selected_variations.length > 0) {
                sellingDetail.selected_variations.forEach(v => {
                  printerAction.text(`    - ${v.variation_name}: ${v.option_name}`);
                });
              }
              if (sellingDetail.note) {
                printerAction.text(`    * ${sellingDetail.note}`);
              }
            });

            printerAction.text(dividerLine)
              .table(['TOTAL ITEM', `${totalQty} Porsi/Item`])
              .align('center');

            if (printerData.footer) {
              printerAction.text(printerData.footer);
            }
          }

          const printSuccess = await printerAction.cut().print(printerData.device_id || printerData.printerId || 'kasir');
          if (printSuccess) {
            anyPrinted = true;
          }
        }
      }

        if (anyPrinted && isAutomatic) {
          new FilamentNotification()
            .title('@lang('Pencetakan Multi-Printer Otomatis Selesai')')
            .body('@lang('Struk kasir dan nota pesanan (Dapur/Bar) telah otomatis diproses.')')
            .success()
            .send();
        }

        if (!anyPrinted && !isAutomatic) {
          new FilamentNotification()
            .title('@lang('Printer tidak terhubung atau gagal mencetak')')
            .body('@lang('Struk dialihkan ke format PDF dan dibuka untuk dicetak.')')
            .warning()
            .send();

          window.open('/member/sellings/' + currentSelling.id + '/receipt-pdf?width=' + defaultPaperWidth, '_blank');
        }
      } catch (error) {
        console.error(error);
        if (!isAutomatic) {
          new FilamentNotification()
            .title('@lang('Terjadi kendala pada printer fisik')')
            .body('@lang('Struk otomatis dialihkan ke format PDF.')')
            .warning()
            .send();

          window.open('/member/sellings/' + currentSelling.id + '/receipt-pdf?width=' + defaultPaperWidth, '_blank');
        }
      }
    }

    async function executeDraftPrint(draft) {
      if (!draft || !Array.isArray(draft.items_to_print) || draft.items_to_print.length === 0) {
        return;
      }

      const dbPrinters = @js(\App\Models\Tenants\Printer::active()->orderBy('id')->get()->map(fn($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'printerId' => $p->device_id,
        'device_id' => $p->device_id,
        'device_name' => $p->device_name,
        'driver' => $p->connection_type,
        'paper_width' => $p->paper_width ?: '58mm',
        'print_all_categories' => (bool) $p->print_all_categories,
        'category_ids' => array_map('intval', $p->category_ids ?? []),
        'show_price' => (bool) $p->show_price,
        'print_on_draft' => (bool) ($p->print_on_draft ?? !$p->show_price),
        'header' => $p->header,
        'footer' => $p->footer,
        'copies' => (int) ($p->copies ?: 1),
      ])->values());

      const legacyPrinter = getPrinter();
      const printersList = (Array.isArray(dbPrinters) && dbPrinters.length > 0)
        ? dbPrinters
        : ((legacyPrinter && !(legacyPrinter instanceof Error) && (legacyPrinter.printerId || window.LakasirBridge)) ? [{
          name: legacyPrinter.name || 'Printer Kasir',
          printerId: legacyPrinter.printerId || 'kasir',
          device_id: legacyPrinter.printerId || 'kasir',
          driver: legacyPrinter.driver || 'usb',
          paper_width: legacyPrinter.paper_width || '58mm',
          print_all_categories: true,
          category_ids: [],
          show_price: false,
          print_on_draft: true,
          header: legacyPrinter.header,
          footer: legacyPrinter.footer,
          copies: 1,
        }] : []);

      if (printersList.length === 0) {
        return;
      }

      // Jika background service print bridge sedang aktif, server queue sudah menangani cetak draf:
      if (window.LakasirBridge && typeof window.LakasirBridge.isServiceRunning === 'function' && window.LakasirBridge.isServiceRunning()) {
        console.log('Background Print Bridge aktif; memicu pencetakan otomatis draf via server queue...');
        if (typeof window.LakasirBridge.triggerPoll === 'function') {
          window.LakasirBridge.triggerPoll();
        }
        return;
      }

      let anyPrinted = false;

      // Hanya cetak ke printer yang diset sebagai printer dapur / checker / print_on_draft
      for (const printerData of printersList) {
        const isDraftPrinter = Boolean(printerData.print_on_draft) || !printerData.show_price;

        if (!isDraftPrinter) {
          continue;
        }

        // Filter item berdasarkan kategori printer station
        const allItems = draft.items_to_print;
        const filteredItems = printerData.print_all_categories
          ? allItems
          : allItems.filter(item => {
            const catId = item.product && item.product.category_id ? parseInt(item.product.category_id, 10) : null;
            return catId !== null && Array.isArray(printerData.category_ids) && printerData.category_ids.includes(catId);
          });

        if (filteredItems.length === 0) {
          continue;
        }

        const paperWidth = printerData.paper_width || '58mm';
        const dividerLine = paperWidth === '80mm'
          ? '------------------------------------------------'
          : '-------------------------------';

        const targetDeviceId = printerData.device_id || printerData.printerId || 'kasir';
        const numCopies = Math.max(1, parseInt(printerData.copies || 1));

        for (let copyIndex = 1; copyIndex <= numCopies; copyIndex++) {
          const printer = new Printer(targetDeviceId, paperWidth);
          let printerAction = printer.font('a');

          let stationTitle = (printerData.name || 'ORDER PESANAN').toUpperCase();
          if (draft.is_reprint) {
            stationTitle += ' (SALINAN)';
          } else if (draft.has_additions) {
            stationTitle += ' (TAMBAHAN)';
          }

          printerAction.size(1)
            .align('center')
            .text('*** ' + stationTitle + ' ***')
            .size(0);

          if (printerData.header) {
            printerAction.text(printerData.header);
          }

          printerAction.align('left')
            .text(dividerLine)
            .table(['Draf / Order', draft.name || ('#' + draft.id)])
            .table(['Kasir/Waitress', draft.user ? draft.user.name : '-']);

          if (draft.order_type_label && draft.order_type_label !== '-') {
            printerAction.table(['Tipe', draft.order_type_label]);
          }
          if (draft.table && draft.table.number) {
            printerAction.table(['Meja', String(draft.table.number)]);
          }
          printerAction.table(['Waktu', draft.created_at || '']);
          printerAction.text(dividerLine);

          let totalQty = 0;
          filteredItems.forEach(item => {
            totalQty += Number(item.qty || 1);
            let itemPrefix = item.is_additional ? '[TAMBAHAN] ' : '';
            printerAction.text(`${itemPrefix}${item.qty}x  ${item.product.name}`);

            if (item.selected_variations && item.selected_variations.length > 0) {
              item.selected_variations.forEach(v => {
                printerAction.text(`    - ${v.variation_name}: ${v.option_name}`);
              });
            }
            if (item.note) {
              printerAction.text(`    * ${item.note}`);
            }
            if (printerData.show_price && item.price) {
              printerAction.align('right').text(moneyFormat(item.price * item.qty)).align('left');
            }
          });

          printerAction.text(dividerLine)
            .table(['TOTAL ITEM', `${totalQty} Porsi/Item`])
            .align('center')
            .text('[STATUS: DRAF - SEGERA DISIAPKAN]')
            .align('center');

          if (printerData.footer) {
            printerAction.text(printerData.footer);
          }

          try {
            const printSuccess = await printerAction.cut().print(targetDeviceId);
            if (printSuccess) {
              anyPrinted = true;
            }
          } catch (err) {
            console.error('Draft print error:', err);
          }
        }
      }

      if (anyPrinted) {
        new FilamentNotification()
          .title('@lang('Tiket Draf Otomatis Tercetak')')
          .body('@lang('Pesanan draf telah otomatis dikirim ke printer station terkait.')')
          .success()
          .send();
      }
    }

    async function executeOpenShiftPrint(slip) {
      if (!slip) return;

      const dbPrinters = @js(\App\Models\Tenants\Printer::active()->orderBy('id')->get()->map(fn($p) => [
        'id' => $p->id,
        'device_id' => $p->device_id,
        'paper_width' => $p->paper_width ?: '58mm',
        'show_price' => (bool) $p->show_price,
      ])->values());

      const legacyPrinter = getPrinter();
      const targetPrinter = (Array.isArray(dbPrinters) && dbPrinters.length > 0)
        ? (dbPrinters.find(p => p.show_price) || dbPrinters[0])
        : {
          device_id: (legacyPrinter && !(legacyPrinter instanceof Error) && legacyPrinter.printerId) ? legacyPrinter.printerId : 'kasir',
          paper_width: (legacyPrinter && !(legacyPrinter instanceof Error) && legacyPrinter.paper_width) ? legacyPrinter.paper_width : '58mm',
        };

      const paperWidth = targetPrinter.paper_width || '58mm';
      const dividerLine = paperWidth === '80mm'
        ? '------------------------------------------------'
        : '-------------------------------';

      const printer = new Printer(targetPrinter.device_id || 'kasir', paperWidth);
      let printerAction = printer.font('a');

      printerAction.size(1)
        .align('center')
        .text('*** BUKA KASIR ***')
        .size(0);

      if (slip.shop_name) {
        printerAction.align('center').text(slip.shop_name);
      }
      if (slip.shop_location) {
        printerAction.align('center').text(slip.shop_location);
      }

      printerAction.align('left')
        .text(dividerLine)
        .table(['Shift No.', '#' + slip.drawer_id])
        .table(['Kasir', slip.cashier_name])
        .table(['Waktu Buka', slip.opened_at])
        .text(dividerLine)
        .align('center')
        .text('[STATUS: SHIFT AKTIF]')
        .text('\n')
        .cut();

      await printerAction.print(targetPrinter.device_id || 'kasir');
    }

    async function executeCloseShiftPrint(report) {
      if (!report) return;

      const dbPrinters = @js(\App\Models\Tenants\Printer::active()->orderBy('id')->get()->map(fn($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'device_id' => $p->device_id,
        'paper_width' => $p->paper_width ?: '58mm',
        'show_price' => (bool) $p->show_price,
      ])->values());

      const legacyPrinter = getPrinter();
      const targetPrinter = (Array.isArray(dbPrinters) && dbPrinters.length > 0)
        ? (dbPrinters.find(p => p.show_price) || dbPrinters[0])
        : {
          device_id: (legacyPrinter && !(legacyPrinter instanceof Error) && legacyPrinter.printerId) ? legacyPrinter.printerId : 'kasir',
          paper_width: (legacyPrinter && !(legacyPrinter instanceof Error) && legacyPrinter.paper_width) ? legacyPrinter.paper_width : '58mm',
        };

      const paperWidth = targetPrinter.paper_width || '58mm';
      const dividerLine = paperWidth === '80mm'
        ? '------------------------------------------------'
        : '-------------------------------';

      const printer = new Printer(targetPrinter.device_id || 'kasir', paperWidth);
      let printerAction = printer.font('a');

      let title = report.is_temporary ? 'CEK SHIFT SEMENTARA (X-REPORT)' : 'LAPORAN TUTUP KASIR (Z-REPORT)';

      printerAction.size(1)
        .align('center')
        .text('*** ' + title + ' ***')
        .size(0);

      if (report.shop_name) {
        printerAction.align('center').text(report.shop_name);
      }
      if (report.shop_location) {
        printerAction.align('center').text(report.shop_location);
      }

      printerAction.align('left')
        .text(dividerLine)
        .table(['Shift No.', '#' + report.drawer_id])
        .table(['Kasir Buka', report.opened_by || '-'])
        .table(['Waktu Buka', report.opened_at || '-'])
        .table(['Kasir Tutup', report.closed_by || '-'])
        .table(['Waktu Tutup', report.closed_at || '-'])
        .text(dividerLine);

      // CETAK REKAP ITEM TERJUAL & JUMLAHNYA
      printerAction.align('center')
        .text('--- REKAP ITEM TERJUAL ---')
        .align('left');

      if (Array.isArray(report.sold_items) && report.sold_items.length > 0) {
        report.sold_items.forEach(item => {
          printerAction.table([
            item.name,
            item.qty + 'x  ' + moneyFormat(item.total)
          ]);
        });
        printerAction.text(dividerLine)
          .table(['TOTAL ITEM TERJUAL', report.total_qty_sold + ' Porsi/Pcs']);
      } else {
        printerAction.align('center').text('Belum ada item terjual').align('left');
      }

      printerAction.text(dividerLine)
        .align('center')
        .text('--- RINGKASAN PEMBAYARAN ---')
        .align('left');

      if (Array.isArray(report.payment_breakdown)) {
        report.payment_breakdown.forEach(pay => {
          printerAction.table([pay.name + ' (' + pay.count + ' tx)', moneyFormat(pay.total)]);
        });
      }

      printerAction.text(dividerLine)
        .table(['Total Transaksi', String(report.total_transactions)])
        .table(['Total Penjualan', moneyFormat(report.total_grand_total)]);

      if (report.total_discounts > 0) {
        printerAction.table(['Total Diskon', '(' + moneyFormat(report.total_discounts) + ')']);
      }

      printerAction.text(dividerLine)
        .table(['TOTAL KAS DI LACI', moneyFormat(report.expected_cash_in_drawer)])
        .align('center')
        .text('(Penjualan Tunai)')
        .text(dividerLine)
        .text('\n\nTanda Tangan Kasir,\n\n\n')
        .text('( ' + (report.closed_by || 'Kasir') + ' )')
        .text('\n')
        .cut();

      await printerAction.print(targetPrinter.device_id || 'kasir');
    }

    document.getElementById("printReceiptButton").addEventListener('click', async (event) => {
      await executeMultiPrinterPrint(selling, false);
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
    Alpine.data('posApp', () => ({
      mobileTab: 'products',
      selectedCategoryId: null,
      searchQuery: '',
      viewMode: 'grid',
      products: @js($posProducts),
      categories: @js($posCategories),
      vouchers: @js($posVouchers),
      members: @js($posMembers),
      tables: @js($posTables),
      drafts: @js($posDrafts),
      paymentMethods: @js($paymentMethods),
      features: @js($posFeatures),
      tax: Number(@js($tax)) || 0,
      minimumStock: Number(@js(\App\Models\Tenants\Setting::get('minimum_stock_nofication', 10))) || 10,

      cart: [],
      orderType: null,
      activeDraftId: null,
      draftName: '',
      memberId: null,
      memberLabel: '',
      memberSearch: '',
      tableId: null,
      tableLabel: '',
      voucherCode: null,
      voucherDiscount: 0,
      manualDiscount: 0,
      manualDiscountInput: '',
      orderNote: '',
      paymentMethodId: (@js($paymentMethods)[0]?.id) || 1,
      dueDate: '',
      payedMoney: 0,
      displayPayedMoney: '',
      displayValue: '',
      paymentError: '',
      isSubmitting: false,

      variationModalProduct: null,
      variationSelections: {},
      variationQty: 1,
      priceSettingItem: null,
      _uidCounter: 1,

      init() {
        window.__posHandleBarcode = (code) => this.handleBarcodeScan(code);
        window.__posCalcShortcut = (val) => this.calcShortcut(val);
      },

      formatMoney(value) {
        return moneyFormat(Number(value) || 0);
      },

      categoryEmoji(name) {
        const lower = (name || '').toLowerCase();
        if (lower.includes('makan')) return '🍲';
        if (lower.includes('camil') || lower.includes('snack')) return '🍟';
        if (lower.includes('minum') || lower.includes('drink')) return '🥤';
        if (lower.includes('tutup') || lower.includes('dessert')) return '🍧';
        return '🍽️';
      },

      orderTypeLabel(type) {
        if (type === 'dine_in') return 'Dine In';
        if (type === 'take_away') return 'Take Away';
        if (type === 'ojek_online') return 'Ojek Online';
        return '-';
      },

      get filteredProducts() {
        const q = (this.searchQuery || '').trim().toLowerCase();
        return this.products.filter((p) => {
          if (this.selectedCategoryId !== null && p.category_id !== this.selectedCategoryId) {
            return false;
          }
          if (!q) return true;
          if ((p.name || '').toLowerCase().includes(q)) return true;
          if ((p.sku || '').toLowerCase().includes(q)) return true;
          if (Array.isArray(p.barcodes) && p.barcodes.some((b) => (b || '').toLowerCase().includes(q))) return true;
          return false;
        });
      },

      get filteredMembers() {
        const q = (this.memberSearch || '').trim().toLowerCase();
        if (!q) return this.members;
        return this.members.filter((m) =>
          (m.name || '').toLowerCase().includes(q) ||
          (m.email || '').toLowerCase().includes(q) ||
          (m.code || '').toLowerCase().includes(q)
        );
      },

      get eligibleVouchers() {
        return this.vouchers.filter((v) => v.minimal_buying <= this.subTotal);
      },

      get activeVoucher() {
        if (!this.voucherCode) return null;
        return this.vouchers.find((v) => v.code === this.voucherCode) || null;
      },

      voucherBadgeLabel(v) {
        if (!v) return '';
        return v.type === 'percentage' ? `${v.nominal}%` : this.formatMoney(v.nominal);
      },

      get totalCartQty() {
        return this.cart.reduce((sum, item) => sum + (Number(item.qty) || 0), 0);
      },

      get subTotal() {
        return this.cart.reduce((sum, item) => sum + (Number(item.price) || 0), 0);
      },

      get itemDiscountsTotal() {
        return this.cart.reduce((sum, item) => sum + (Number(item.discount_price) || 0), 0);
      },

      get totalDiscount() {
        return this.itemDiscountsTotal + (Number(this.voucherDiscount) || 0) + (Number(this.manualDiscount) || 0);
      },

      get totalPrice() {
        const raw = this.subTotal + (this.subTotal * this.tax / 100) - this.totalDiscount;
        return Math.max(0, raw);
      },

      get moneyChanges() {
        return (Number(this.payedMoney) || 0) - this.totalPrice;
      },

      get hasMissingRequiredVariations() {
        return this.cart.some((item) => this.itemHasMissingRequired(item));
      },

      get hasMissingOrderType() {
        return !this.orderType;
      },

      getProductCartQty(productId) {
        return this.cart
          .filter((i) => i.product_id === productId)
          .reduce((s, i) => s + (Number(i.qty) || 0), 0);
      },

      get variationModalTotalPrice() {
        if (!this.variationModalProduct) return 0;
        let extra = 0;
        for (const v of (this.variationModalProduct.variations || [])) {
          if (v.type === 'single') {
            const optId = this.variationSelections[v.id];
            const opt = (v.options || []).find((o) => o.id === optId);
            if (opt) extra += Number(opt.price) || 0;
          } else {
            const optIds = this.variationSelections[v.id] || [];
            for (const optId of optIds) {
              const opt = (v.options || []).find((o) => o.id === optId);
              if (opt) extra += Number(opt.price) || 0;
            }
          }
        }
        return ((Number(this.variationModalProduct.selling_price) || 0) + extra) * Math.max(1, this.variationQty);
      },

      get canSubmitVariationModal() {
        if (!this.variationModalProduct) return false;
        for (const v of (this.variationModalProduct.variations || [])) {
          if (v.is_required) {
            if (v.type === 'single') {
              if (!this.variationSelections[v.id]) return false;
            } else {
              const sel = this.variationSelections[v.id] || [];
              if (sel.length < Math.max(1, v.min || 1)) return false;
            }
          }
        }
        return true;
      },

      selectProduct(product) {
        const defaultVariations = [];
        for (const v of (product.variations || [])) {
          if (v.type === 'single') {
            const def = (v.options || []).find((o) => o.is_default);
            if (def) {
              defaultVariations.push({
                variation_id: v.id,
                variation_name: v.name,
                option_id: def.id,
                option_name: def.name,
                price: Number(def.price) || 0,
              });
            }
          } else {
            for (const def of (v.options || []).filter((o) => o.is_default)) {
              defaultVariations.push({
                variation_id: v.id,
                variation_name: v.name,
                option_id: def.id,
                option_name: def.name,
                price: Number(def.price) || 0,
              });
            }
          }
        }
        this.addProductToCart(product, 1, defaultVariations);
      },

      openVariationModal(product) {
        this.variationModalProduct = product;
        this.variationQty = 1;
        const sel = {};
        for (const v of (product.variations || [])) {
          if (v.type === 'single') {
            const def = (v.options || []).find((o) => o.is_default) || (v.is_required ? (v.options || [])[0] : null);
            sel[v.id] = def ? def.id : null;
          } else {
            sel[v.id] = (v.options || []).filter((o) => o.is_default).map((o) => o.id);
          }
        }
        this.variationSelections = sel;
        this.$dispatch('open-modal', { id: 'variation-modal' });
      },

      selectModalVariationOption(variationId, optionId, type) {
        if (type === 'single') {
          this.variationSelections[variationId] = optionId;
        } else {
          const current = this.variationSelections[variationId] || [];
          if (current.includes(optionId)) {
            this.variationSelections[variationId] = current.filter((id) => id !== optionId);
          } else {
            this.variationSelections[variationId] = [...current, optionId];
          }
        }
      },

      isModalOptionSelected(variationId, optionId, type) {
        if (type === 'single') {
          return this.variationSelections[variationId] === optionId;
        }
        return (this.variationSelections[variationId] || []).includes(optionId);
      },

      confirmVariationModal() {
        if (!this.variationModalProduct || !this.canSubmitVariationModal) return;
        const selectedVariations = [];
        for (const v of (this.variationModalProduct.variations || [])) {
          if (v.type === 'single') {
            const optId = this.variationSelections[v.id];
            const opt = (v.options || []).find((o) => o.id === optId);
            if (opt) {
              selectedVariations.push({
                variation_id: v.id,
                variation_name: v.name,
                option_id: opt.id,
                option_name: opt.name,
                price: Number(opt.price) || 0,
              });
            }
          } else {
            const optIds = this.variationSelections[v.id] || [];
            for (const optId of optIds) {
              const opt = (v.options || []).find((o) => o.id === optId);
              if (opt) {
                selectedVariations.push({
                  variation_id: v.id,
                  variation_name: v.name,
                  option_id: opt.id,
                  option_name: opt.name,
                  price: Number(opt.price) || 0,
                });
              }
            }
          }
        }
        this.addProductToCart(this.variationModalProduct, Math.max(1, this.variationQty), selectedVariations);
        this.$dispatch('close-modal', { id: 'variation-modal' });
      },

      variationSignature(vars) {
        const list = Array.isArray(vars) ? [...vars] : [];
        list.sort((a, b) => (a.option_id || 0) - (b.option_id || 0));
        return list.map((v) => `${v.variation_id}:${v.option_id}`).join('|');
      },

      computeUnitPrice(baseSellingPrice, selectedVariations, priceUnit) {
        if (priceUnit) {
          return Number(priceUnit.selling_price) || 0;
        }
        const extra = (selectedVariations || []).reduce((s, v) => s + (Number(v.price) || 0), 0);
        return (Number(baseSellingPrice) || 0) + extra;
      },

      checkStockAvailable(product, addQty) {
        if (product.is_non_stock) return true;
        const inCart = this.getProductCartQty(product.id);
        if (product.stock < 0 || product.stock < (inCart + addQty)) {
          new FilamentNotification()
            .title('{{ __('Stock is out') }}')
            .danger()
            .send();
          return false;
        }
        return true;
      },

      addProductToCart(product, qty = 1, selectedVariations = []) {
        if (!this.checkStockAvailable(product, qty)) return;

        const sig = this.variationSignature(selectedVariations);
        const existing = this.cart.find((item) =>
          item.product_id === product.id &&
          !item.price_unit_id &&
          this.variationSignature(item.selected_variations) === sig
        );

        if (existing) {
          existing.qty += qty;
          this.recalculateCartItem(existing);
        } else {
          const unitPrice = this.computeUnitPrice(product.selling_price, selectedVariations, null);
          this.cart.unshift({
            uid: 'ci_' + (this._uidCounter++),
            product_id: product.id,
            name: product.name,
            base_unit_price: Number(product.selling_price) || 0,
            unit_price: unitPrice,
            qty: qty,
            price: unitPrice * qty,
            discount_price: 0,
            price_unit_id: null,
            price_unit_label: null,
            selected_variations: selectedVariations,
            note: '',
            variations: product.variations || [],
            price_units: product.price_units || [],
            expanded: (product.variations || []).some((v) => {
              if (!v.is_required) return false;
              const count = selectedVariations.filter((sv) => sv.variation_id === v.id).length;
              return count < Math.max(1, v.min || 1);
            }),
          });
        }
        this.recalculateVoucher();
      },

      addSeparateVariationItem(productId) {
        const product = this.products.find((p) => p.id === productId);
        if (!product || !this.checkStockAvailable(product, 1)) return;

        const defaultVars = [];
        for (const v of (product.variations || [])) {
          const def = (v.options || []).find((o) => o.is_default);
          if (def) {
            defaultVars.push({
              variation_id: v.id,
              variation_name: v.name,
              option_id: def.id,
              option_name: def.name,
              price: Number(def.price) || 0,
            });
          }
        }
        const unitPrice = this.computeUnitPrice(product.selling_price, defaultVars, null);
        this.cart.unshift({
          uid: 'ci_' + (this._uidCounter++),
          product_id: product.id,
          name: product.name,
          base_unit_price: Number(product.selling_price) || 0,
          unit_price: unitPrice,
          qty: 1,
          price: unitPrice,
          discount_price: 0,
          price_unit_id: null,
          price_unit_label: null,
          selected_variations: defaultVars,
          note: '',
          variations: product.variations || [],
          price_units: product.price_units || [],
          expanded: true,
        });
        this.recalculateVoucher();
      },

      recalculateCartItem(item) {
        const product = this.products.find((p) => p.id === item.product_id);
        const basePrice = product ? Number(product.selling_price) : Number(item.base_unit_price || 0);
        const priceUnit = (item.price_units || []).find((pu) => pu.id === item.price_unit_id) || null;
        item.unit_price = this.computeUnitPrice(basePrice, item.selected_variations, priceUnit);
        item.price = item.unit_price * (Number(item.qty) || 0);
        this.recalculateVoucher();
      },

      increaseItem(uid) {
        const item = this.cart.find((i) => i.uid === uid);
        if (!item) return;
        const product = this.products.find((p) => p.id === item.product_id);
        if (product && !this.checkStockAvailable(product, 1)) return;
        item.qty = (Number(item.qty) || 0) + 1;
        this.recalculateCartItem(item);
      },

      reduceItem(uid) {
        const idx = this.cart.findIndex((i) => i.uid === uid);
        if (idx === -1) return;
        if (this.cart[idx].qty <= 1) {
          this.cart.splice(idx, 1);
        } else {
          this.cart[idx].qty -= 1;
          this.recalculateCartItem(this.cart[idx]);
        }
        this.recalculateVoucher();
      },

      setItemQty(uid, rawVal) {
        const item = this.cart.find((i) => i.uid === uid);
        if (!item) return;
        const qty = parseInt(rawVal, 10);
        if (isNaN(qty) || qty <= 0) {
          this.deleteItem(uid);
          return;
        }
        const product = this.products.find((p) => p.id === item.product_id);
        const diff = qty - item.qty;
        if (product && diff > 0 && !this.checkStockAvailable(product, diff)) {
          return;
        }
        item.qty = qty;
        this.recalculateCartItem(item);
      },

      deleteItem(uid) {
        this.cart = this.cart.filter((i) => i.uid !== uid);
        this.recalculateVoucher();
      },

      clearCart() {
        this.cart = [];
        this.activeDraftId = null;
        this.draftName = '';
        this.orderType = null;
        this.memberId = null;
        this.memberLabel = '';
        this.tableId = null;
        this.tableLabel = '';
        this.voucherCode = null;
        this.voucherDiscount = 0;
        this.manualDiscount = 0;
        this.manualDiscountInput = '';
        this.orderNote = '';
        this.payedMoney = 0;
        this.displayPayedMoney = '';
        this.displayValue = '';
        this.paymentError = '';
      },

      isOptionSelectedOnItem(item, variationId, optionId) {
        return (item.selected_variations || []).some((v) => v.variation_id === variationId && v.option_id === optionId);
      },

      hasSelectedForVariation(item, variationId) {
        return (item.selected_variations || []).some((v) => v.variation_id === variationId);
      },

      isVariationMissingOnItem(item, variation) {
        if (!variation.is_required) return false;
        const count = (item.selected_variations || []).filter((v) => v.variation_id === variation.id).length;
        return count < Math.max(1, variation.min || 1);
      },

      itemHasMissingRequired(item) {
        return (item.variations || []).some((v) => this.isVariationMissingOnItem(item, v));
      },

      toggleInlineVariationOption(uid, variation, option) {
        const item = this.cart.find((i) => i.uid === uid);
        if (!item) return;
        let current = [...(item.selected_variations || [])];
        if (variation.type === 'single') {
          current = current.filter((v) => v.variation_id !== variation.id);
          current.push({
            variation_id: variation.id,
            variation_name: variation.name,
            option_id: option.id,
            option_name: option.name,
            price: Number(option.price) || 0,
          });
        } else {
          const exists = current.some((v) => v.variation_id === variation.id && v.option_id === option.id);
          if (exists) {
            current = current.filter((v) => !(v.variation_id === variation.id && v.option_id === option.id));
          } else {
            current.push({
              variation_id: variation.id,
              variation_name: variation.name,
              option_id: option.id,
              option_name: option.name,
              price: Number(option.price) || 0,
            });
          }
        }
        item.selected_variations = current;
        this.recalculateCartItem(item);
      },

      clearInlineVariation(uid, variationId) {
        const item = this.cart.find((i) => i.uid === uid);
        if (!item) return;
        item.selected_variations = (item.selected_variations || []).filter((v) => v.variation_id !== variationId);
        this.recalculateCartItem(item);
      },

      openPriceSettingModal(item) {
        this.priceSettingItem = item;
        this.$dispatch('open-modal', { id: 'modal-price-setting' });
      },

      applyPriceUnit(priceUnit) {
        if (!this.priceSettingItem) return;
        this.priceSettingItem.price_unit_id = priceUnit ? priceUnit.id : null;
        this.priceSettingItem.price_unit_label = priceUnit ? priceUnit.unit : null;
        this.recalculateCartItem(this.priceSettingItem);
        this.$dispatch('close-modal', { id: 'modal-price-setting' });
      },

      selectMember(member) {
        this.memberId = member.id;
        this.memberLabel = member.name;
      },

      clearMember() {
        this.memberId = null;
        this.memberLabel = '';
      },

      selectTable(table) {
        this.tableId = table.id;
        this.tableLabel = table.number;
      },

      clearTable() {
        this.tableId = null;
        this.tableLabel = '';
      },

      assignVoucher(code) {
        const v = this.vouchers.find((item) => item.code === code);
        if (!v || v.minimal_buying > this.subTotal || v.kuota <= 0) {
          new FilamentNotification()
            .title('{{ __('Voucher not found') }}')
            .warning()
            .send();
          return;
        }
        this.voucherCode = v.code;
        this.recalculateVoucher();
      },

      removeVoucher() {
        this.voucherCode = null;
        this.voucherDiscount = 0;
      },

      recalculateVoucher() {
        if (!this.voucherCode) {
          this.voucherDiscount = 0;
          return;
        }
        const v = this.vouchers.find((item) => item.code === this.voucherCode);
        if (!v || v.minimal_buying > this.subTotal) {
          this.voucherCode = null;
          this.voucherDiscount = 0;
          return;
        }
        const baseForVoucher = Math.max(0, this.subTotal + (this.subTotal * this.tax / 100) - this.itemDiscountsTotal - (Number(this.manualDiscount) || 0));
        if (v.type === 'percentage') {
          this.voucherDiscount = baseForVoucher * (Number(v.nominal) || 0) / 100;
        } else {
          this.voucherDiscount = Number(v.nominal) || 0;
        }
      },

      applyManualDiscount() {
        const val = parseFloat(this.manualDiscountInput);
        this.manualDiscount = (!isNaN(val) && val > 0) ? val : 0;
        this.recalculateVoucher();
      },

      notifyMissingVariations() {
        new FilamentNotification()
          .title('{{ __('Variasi Wajib Belum Dipilih') }}')
          .body('{{ __('Harap lengkapi semua variasi bertanda * Wajib pada item pesanan terlebih dahulu sebelum melanjutkan pembayaran.') }}')
          .danger()
          .send();
      },

      notifyMissingOrderType() {
        new FilamentNotification()
          .title('{{ __('Kategori Pesanan Wajib Dipilih') }}')
          .body('{{ __('Silakan pilih kategori pesanan (Dine In, Take Away, atau Ojek Online) terlebih dahulu.') }}')
          .warning()
          .send();
      },

      validateCartReady() {
        if (this.cart.length === 0) {
          new FilamentNotification()
            .title('{{ __('Keranjang Masih Kosong') }}')
            .body('{{ __('Silakan pilih item pesanan terlebih dahulu.') }}')
            .warning()
            .send();
          return false;
        }
        if (this.hasMissingRequiredVariations) {
          this.notifyMissingVariations();
          return false;
        }
        if (this.hasMissingOrderType) {
          this.notifyMissingOrderType();
          return false;
        }
        return true;
      },

      buildDraftPayload() {
        return {
          active_draft_id: this.activeDraftId,
          draft_name: this.draftName,
          order_type: this.orderType,
          member_id: this.memberId,
          table_id: this.tableId,
          voucher: this.voucherCode,
          discount_price: this.manualDiscount,
          note: this.orderNote,
          total_price: this.totalPrice,
          items: this.cart.map((item) => ({
            product_id: item.product_id,
            qty: Number(item.qty) || 1,
            price: Number(item.price) || 0,
            discount_price: Number(item.discount_price) || 0,
            price_unit_id: item.price_unit_id || null,
            selected_variations: item.selected_variations || [],
            note: item.note || null,
          })),
        };
      },

      async quickSaveDraft() {
        if (!this.validateCartReady() || this.isSubmitting) return;
        this.isSubmitting = true;
        try {
          const res = await $wire.saveFrontendDraft(this.buildDraftPayload(), true);
          if (res && res.ok) {
            this.drafts = res.drafts || [];
            if (res.clear_cart) {
              this.clearCart();
            }
          }
        } finally {
          this.isSubmitting = false;
        }
      },

      openSaveDraftModal() {
        if (!this.validateCartReady()) return;
        if (!this.draftName || !this.draftName.trim()) {
          const tablePart = this.tableLabel ? (' - Meja ' + this.tableLabel) : '';
          this.draftName = this.orderTypeLabel(this.orderType) + tablePart + ' #' + (this.drafts.length + 1);
        }
        this.$dispatch('open-modal', { id: 'save-draft-modal' });
      },

      async submitCustomDraft() {
        if (!this.validateCartReady() || this.isSubmitting) return;
        this.isSubmitting = true;
        try {
          const res = await $wire.saveFrontendDraft(this.buildDraftPayload(), true);
          if (res && res.ok) {
            this.drafts = res.drafts || [];
            this.$dispatch('close-modal', { id: 'save-draft-modal' });
            if (res.clear_cart) {
              this.clearCart();
            }
          }
        } finally {
          this.isSubmitting = false;
        }
      },

      async saveAndProceedToPayment() {
        if (!this.validateCartReady() || this.isSubmitting) return;
        this.isSubmitting = true;
        this.paymentError = '';
        try {
          const res = await $wire.saveFrontendDraft(this.buildDraftPayload(), false);
          if (res && res.ok) {
            this.activeDraftId = res.active_draft_id;
            this.draftName = res.draft_name;
            this.drafts = res.drafts || [];
            this.payedMoney = 0;
            this.displayPayedMoney = '';
            this.displayValue = '';
            if (this.features.payment_shortcut) {
              generateButton(this.totalPrice);
            }
            this.$dispatch('open-modal', { id: 'proceed-the-payment' });
          }
        } finally {
          this.isSubmitting = false;
        }
      },

      async submitPayment() {
        if (this.isSubmitting) return;
        this.paymentError = '';
        const selectedPm = this.paymentMethods.find((pm) => pm.id == this.paymentMethodId);
        const isCredit = selectedPm ? Boolean(selectedPm.is_credit) : false;

        if (!isCredit && (Number(this.payedMoney) || 0) < this.totalPrice) {
          this.paymentError = '{{ __('Nominal pembayaran kurang dari total tagihan.') }}';
          return;
        }
        if (isCredit && (!this.memberId || !this.dueDate)) {
          this.paymentError = '{{ __('Pembayaran kredit wajib memilih member dan tanggal jatuh tempo.') }}';
          return;
        }

        this.isSubmitting = true;
        try {
          const payload = {
            ...this.buildDraftPayload(),
            payment_method_id: this.paymentMethodId,
            payed_money: Number(this.payedMoney) || 0,
            due_date: this.dueDate || null,
            friend_price: false,
          };
          const res = await $wire.proceedFrontendPayment(payload);
          if (res && res.ok) {
            if (Array.isArray(res.products)) this.products = res.products;
            if (Array.isArray(res.drafts)) this.drafts = res.drafts;
            this.clearCart();
          }
        } catch (err) {
          this.paymentError = err?.message || '{{ __('Terjadi kesalahan saat memproses pembayaran.') }}';
        } finally {
          this.isSubmitting = false;
        }
      },

      loadDraftFromList(draft) {
        if (!draft) return;
        const loadedCart = [];
        for (const itemData of (draft.items || [])) {
          const product = this.products.find((p) => p.id === itemData.product_id);
          const priceUnits = product ? (product.price_units || []) : [];
          const pu = priceUnits.find((u) => u.id === itemData.price_unit_id) || null;
          const qty = Number(itemData.qty) || 1;
          const price = Number(itemData.price) || 0;
          loadedCart.push({
            uid: 'ci_' + (this._uidCounter++),
            product_id: itemData.product_id,
            name: itemData.product_name || product?.name || 'Item',
            base_unit_price: product ? Number(product.selling_price) : (qty > 0 ? price / qty : price),
            unit_price: qty > 0 ? price / qty : price,
            qty: qty,
            price: price,
            discount_price: Number(itemData.discount_price) || 0,
            price_unit_id: itemData.price_unit_id || null,
            price_unit_label: pu ? pu.unit : null,
            selected_variations: itemData.selected_variations || [],
            note: itemData.note || '',
            variations: product ? (product.variations || []) : [],
            price_units: priceUnits,
            expanded: false,
          });
        }

        this.cart = loadedCart;
        this.activeDraftId = draft.id;
        this.draftName = draft.name || '';
        this.orderType = draft.order_type || null;
        this.memberId = draft.member_id || null;
        this.memberLabel = draft.member_name || '';
        this.tableId = draft.table_id || null;
        this.tableLabel = draft.table_number || '';
        this.voucherCode = draft.voucher || null;
        this.manualDiscount = Number(draft.discount_price) || 0;
        this.manualDiscountInput = this.manualDiscount > 0 ? String(this.manualDiscount) : '';
        this.orderNote = draft.note || '';
        this.recalculateVoucher();

        this.$dispatch('close-modal', { id: 'draft-list-modal' });

        new FilamentNotification()
          .title('{{ __('Draft Pesanan Dimuat') }}')
          .body((draft.name || 'Draft') + ' {{ __('berhasil dimuat ke keranjang.') }}')
          .success()
          .send();
      },

      async deleteDraftFromList(draftId) {
        const res = await $wire.deleteFrontendDraft(draftId);
        if (res && res.ok) {
          this.drafts = res.drafts || [];
          if (this.activeDraftId === draftId) {
            this.activeDraftId = null;
            this.draftName = '';
          }
        }
      },

      async printDraftFromList(draftId) {
        const res = await $wire.printFrontendDraft(draftId);
        if (res && res.ok) {
          this.drafts = res.drafts || [];
        }
      },

      handleBarcodeScan(code) {
        const q = (code || '').trim().toLowerCase();
        if (!q) return;
        const product = this.products.find((p) =>
          (p.sku || '').toLowerCase() === q ||
          (Array.isArray(p.barcodes) && p.barcodes.some((b) => (b || '').toLowerCase() === q))
        );
        if (product) {
          this.selectProduct(product);
          new FilamentNotification()
            .title('Product added')
            .success()
            .duration(2000)
            .send();
        } else {
          new FilamentNotification()
            .title('{{ __('Product not found') }}')
            .warning()
            .duration(2500)
            .send();
        }
      },

      calcShortcut(number) {
        this.payedMoney = Number(number) || 0;
        this.displayValue = this.payedMoney > 0 ? String(this.payedMoney) : '';
        this.displayPayedMoney = moneyFormat(this.payedMoney);
      },

      calcAppend(number) {
        if (number === 'no_changes') {
          this.payedMoney = this.totalPrice;
          this.displayValue = String(this.totalPrice);
          this.displayPayedMoney = moneyFormat(this.totalPrice);
          return;
        }
        if (number === 'backspace') {
          this.displayValue = this.displayValue.slice(0, -1);
          const num = parseInt(this.displayValue.replace(/\D/g, ''), 10) || 0;
          this.payedMoney = num;
          this.displayPayedMoney = moneyFormat(num);
          return;
        }
        this.displayValue += String(number);
        const num = parseInt(this.displayValue.replace(/\D/g, ''), 10) || 0;
        this.payedMoney = num;
        this.displayPayedMoney = moneyFormat(num);
      },
    }));

    let barcodeData = '';
    let barcodeTimeout;
    let scannerEnabled = true;
    let modalOpened = false;

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
      if (!calculatorBtn) return;
      calculatorBtn.innerHTML = '';

      for (let suggestion of shortcutSuggestion) {
        const button = document.createElement('button');
        button.textContent = numberFormat(suggestion);
        button.setAttribute('type', 'button');
        button.addEventListener('click', () => {
          if (typeof window.__posCalcShortcut === 'function') {
            window.__posCalcShortcut(suggestion);
          }
        });
        button.className = 'bg-gray-300 hover:bg-gray-400 p-2 rounded-md text-lg';
        calculatorBtn.appendChild(button);
      }
    }

    $wire.on('open-modal', (event) => {
      if (event.id === 'qr-scanner-modal') {
        if (!html5QrcodeScanner) {
          html5QrcodeScanner = new Html5QrcodeScanner(
            "qr-reader",
            {
              fps: 10,
              qrbox: { width: 300, height: 200 },
              rememberLastUsedCamera: true
            },
            false
          );
        }
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
      }
      modalOpened = true;
    });

    $wire.on('close-modal', (event) => {
      modalOpened = false;
    });

    // QR Scanner global variables and functions
    let html5QrcodeScanner = null;
    let isScanningEnabled = true;

    async function onScanSuccess(decodedText, decodedResult) {
      if (!isScanningEnabled) return;
      isScanningEnabled = false;

      if (typeof window.__posHandleBarcode === 'function') {
        window.__posHandleBarcode(decodedText);
      }

      setTimeout(() => {
        isScanningEnabled = true;
      }, 1000);
    }

    function onScanFailure(error) {
      // Intentionally empty
    }

    window.stopScanner = () => {
      if (html5QrcodeScanner && html5QrcodeScanner.getState() === Html5QrcodeScannerState.SCANNING) {
        html5QrcodeScanner.clear().then(() => {
          console.log('QR Code scanner stopped successfully.');
        }).catch(err => {});
      }
    };

    // Physical barcode scanner support (keyboard input)
    document.addEventListener('keypress', (event) => {
      if (modalOpened || !scannerEnabled) {
        return;
      }

      if (document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
        return;
      }

      if (barcodeTimeout) {
        clearTimeout(barcodeTimeout);
      }

      if (event.key === 'Enter') {
        if (barcodeData && typeof window.__posHandleBarcode === 'function') {
          window.__posHandleBarcode(barcodeData);
        }
        barcodeData = '';
        scannerEnabled = false;
        setTimeout(() => {
          scannerEnabled = true;
        }, 500);
      } else {
        barcodeData += event.key;
      }

      barcodeTimeout = setTimeout(() => {
        barcodeData = '';
      }, 500);
    });
  </script>
  @endscript