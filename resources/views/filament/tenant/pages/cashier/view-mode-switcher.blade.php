<div class="inline-flex items-center rounded-xl border border-gray-200/80 dark:border-gray-700/80 bg-gray-50 dark:bg-gray-800 p-1">
  <button type="button" x-on:click="$wire.setViewMode('grid')" title="{{ __('Grid View') }}"
    :class="$wire.viewMode === 'grid' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200/70 dark:text-gray-300 dark:hover:bg-gray-700'"
    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold transition">
    <x-heroicon-o-squares-2x2 class="h-3.5 w-3.5" />
    <span class="hidden sm:inline">{{ __('Grid') }}</span>
  </button>
  <button type="button" x-on:click="$wire.setViewMode('list')" title="{{ __('List View') }}"
    :class="$wire.viewMode === 'list' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200/70 dark:text-gray-300 dark:hover:bg-gray-700'"
    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold transition">
    <x-heroicon-o-bars-3 class="h-3.5 w-3.5" />
    <span class="hidden sm:inline">{{ __('List') }}</span>
  </button>
  <button type="button" x-on:click="$wire.setViewMode('compact')" title="{{ __('Small Icon') }}"
    :class="$wire.viewMode === 'compact' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-200/70 dark:text-gray-300 dark:hover:bg-gray-700'"
    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold transition">
    <x-heroicon-o-squares-plus class="h-3.5 w-3.5" />
    <span class="hidden sm:inline">{{ __('Small') }}</span>
  </button>
</div>
