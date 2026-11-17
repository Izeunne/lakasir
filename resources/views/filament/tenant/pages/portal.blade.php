<div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
  {{-- Top Header --}}
  <header class="w-full border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-6 py-4">
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        @if ($shopLogo)
          <img src="{{ $shopLogo }}" alt="{{ $shopName }}" class="h-10 w-10 rounded-xl object-cover shadow-sm">
        @else
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-500 text-white font-bold text-lg shadow-sm">
            {{ strtoupper(substr($shopName, 0, 1)) }}
          </div>
        @endif
        <div>
          <h1 class="text-base font-bold text-gray-900 dark:text-white">{{ $shopName }}</h1>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('Perangkat aktif:') }} <span class="font-medium text-gray-700 dark:text-gray-300">{{ auth()->user()?->name }}</span>
          </p>
        </div>
      </div>

      <button
        type="button"
        wire:click="logoutDevice"
        wire:confirm="{{ __('Yakin ingin logout dari perangkat ini?') }}"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
      >
        <x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4" />
        <span>{{ __('Logout') }}</span>
      </button>
    </div>
  </header>

  {{-- Main Launcher Cards --}}
  <main class="flex-1 flex items-center justify-center p-6">
    <div class="w-full max-w-4xl">
      <div class="mb-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
          {{ __('Pilih Mode Kerja') }}
        </h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
          {{ __('Masuk ke halaman kasir untuk transaksi, atau buka Admin Panel untuk pengelolaan toko.') }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        {{-- POS Card --}}
        <button
          type="button"
          wire:click="openPosModal"
          class="group relative flex flex-col items-start rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-left shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-primary-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 group-hover:bg-primary-500 group-hover:text-white transition">
            <x-heroicon-o-shopping-cart class="h-7 w-7" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white">
            {{ __('POS (Kasir)') }}
          </h3>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
            {{ __('Pilih akun kasir dan masukkan PIN untuk mulai melayani transaksi penjualan dan mencetak nota.') }}
          </p>
          <div class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 dark:text-primary-400">
            <span>{{ __('Buka POS') }}</span>
            <x-heroicon-o-arrow-right class="h-4 w-4 transition-transform group-hover:translate-x-1" />
          </div>
        </button>

        {{-- Admin Panel Card --}}
        <button
          type="button"
          wire:click="openAdminModal"
          class="group relative flex flex-col items-start rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-left shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-gray-900 dark:hover:border-gray-500 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-gray-500"
        >
          <div class="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 group-hover:bg-gray-900 dark:group-hover:bg-white group-hover:text-white dark:group-hover:text-gray-900 transition">
            <x-heroicon-o-shield-check class="h-7 w-7" />
          </div>
          <h3 class="text-xl font-bold text-gray-900 dark:text-white">
            {{ __('Admin Panel') }}
          </h3>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
            {{ __('Kelola produk, stok, laporan penjualan, pengguna, dan pengaturan toko. Dilindungi PIN Admin.') }}
          </p>
          <div class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-gray-700 dark:text-gray-300">
            <x-heroicon-o-lock-closed class="h-4 w-4" />
            <span>{{ __('Masuk Admin Panel') }}</span>
          </div>
        </button>
      </div>
    </div>
  </main>

  {{-- POS Modal: Select User + Input PIN --}}
  @if ($activeModal === 'pos')
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div class="w-full max-w-lg rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Masuk ke POS') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Pilih kasir lalu masukkan PIN Anda') }}</p>
          </div>
          <button type="button" wire:click="closeModal" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600">
            <x-heroicon-o-x-mark class="h-5 w-5" />
          </button>
        </div>

        <form wire:submit="enterPos" class="p-6 space-y-5">
          @if ($errorMessage)
            <div class="rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 px-4 py-3 text-sm text-danger-700 dark:text-danger-300">
              {{ $errorMessage }}
            </div>
          @endif

          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
              {{ __('Pilih Kasir') }}
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-56 overflow-y-auto pr-1">
              @foreach ($this->users as $user)
                @php
                  $isSelected = $selectedUserId === $user->id;
                  $roleLabel = $user->is_owner ? 'Owner' : ($user->roles->first()?->name ?? 'User');
                @endphp
                <button
                  type="button"
                  wire:click="selectUser({{ $user->id }})"
                  class="flex items-center justify-between rounded-xl border p-3 text-left transition {{ $isSelected ? 'border-primary-500 bg-primary-50 dark:bg-primary-950/40 ring-1 ring-primary-500' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}"
                >
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $user->name }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $roleLabel }}</p>
                  </div>
                  @if (! $user->hasPin())
                    <span class="ml-2 shrink-0 rounded-md bg-warning-100 dark:bg-warning-900/40 px-2 py-0.5 text-[10px] font-medium text-warning-700 dark:text-warning-300">
                      {{ __('No PIN') }}
                    </span>
                  @endif
                </button>
              @endforeach
            </div>
          </div>

          <div>
            <label for="posPinInput" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
              {{ __('PIN Kasir') }}
            </label>
            <input
              id="posPinInput"
              type="password"
              inputmode="numeric"
              wire:model="posPin"
              placeholder="{{ __('Masukkan 4-8 digit PIN') }}"
              autofocus
              class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 text-center text-lg tracking-[0.4em] font-bold text-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
            >
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              wire:click="closeModal"
              class="rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
            >
              {{ __('Batal') }}
            </button>
            <button
              type="submit"
              class="rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              {{ __('Masuk POS') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- Admin Panel Modal: Input Admin PIN --}}
  @if ($activeModal === 'admin')
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Verifikasi PIN Admin') }}</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Masukkan PIN Admin untuk membuka Admin Panel') }}</p>
          </div>
          <button type="button" wire:click="closeModal" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600">
            <x-heroicon-o-x-mark class="h-5 w-5" />
          </button>
        </div>

        <form wire:submit="enterAdminPanel" class="p-6 space-y-5">
          @if ($errorMessage)
            <div class="rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 px-4 py-3 text-sm text-danger-700 dark:text-danger-300">
              {{ $errorMessage }}
            </div>
          @endif

          <div>
            <label for="adminPinInput" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
              {{ __('PIN Admin') }}
            </label>
            <input
              id="adminPinInput"
              type="password"
              inputmode="numeric"
              wire:model="adminPin"
              placeholder="{{ __('Masukkan PIN Admin') }}"
              autofocus
              class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 text-center text-lg tracking-[0.4em] font-bold text-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500"
            >
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              wire:click="closeModal"
              class="rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
            >
              {{ __('Batal') }}
            </button>
            <button
              type="submit"
              class="rounded-xl bg-gray-900 dark:bg-white px-5 py-2.5 text-sm font-semibold text-white dark:text-gray-900 shadow-sm hover:bg-gray-800 dark:hover:bg-gray-100"
            >
              {{ __('Buka Admin Panel') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  @endif
</div>
