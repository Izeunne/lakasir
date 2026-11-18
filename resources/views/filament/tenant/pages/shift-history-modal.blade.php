<div class="space-y-3 text-xs">
  <p class="text-gray-500 dark:text-gray-400">
    {{ __('Daftar sesi buka & tutup kasir beserta ringkasan transaksi dan item yang terjual.') }}
  </p>

  <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
    <table class="w-full text-left text-xs">
      <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-semibold">
        <tr>
          <th class="px-3.5 py-2.5">#</th>
          <th class="px-3.5 py-2.5">{{ __('Kasir Buka') }}</th>
          <th class="px-3.5 py-2.5">{{ __('Modal Awal') }}</th>
          <th class="px-3.5 py-2.5">{{ __('Waktu Buka') }}</th>
          <th class="px-3.5 py-2.5">{{ __('Kasir Tutup') }}</th>
          <th class="px-3.5 py-2.5">{{ __('Waktu Tutup') }}</th>
          <th class="px-3.5 py-2.5 text-center">{{ __('Status') }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($shifts as $shift)
          @php
            $isClosed = !empty($shift->closed_by);
          @endphp
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
            <td class="px-3.5 py-2.5 font-bold">#{{ $shift->id }}</td>
            <td class="px-3.5 py-2.5 font-medium">{{ $shift->openedBy?->name ?? '-' }}</td>
            <td class="px-3.5 py-2.5 font-semibold text-gray-900 dark:text-white">{{ price_format($shift->cash) }}</td>
            <td class="px-3.5 py-2.5 text-gray-500">{{ $shift->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
            <td class="px-3.5 py-2.5 font-medium">{{ $shift->closedBy?->name ?? '-' }}</td>
            <td class="px-3.5 py-2.5 text-gray-500">{{ $shift->closed_by ? ($shift->updated_at?->format('d/m/Y H:i') ?? '-') : '-' }}</td>
            <td class="px-3.5 py-2.5 text-center">
              @if ($isClosed)
                <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2.5 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300">
                  {{ __('Ditutup') }}
                </span>
              @else
                <span class="rounded-full bg-emerald-100 dark:bg-emerald-950/60 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:text-emerald-400">
                  {{ __('Aktif') }}
                </span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="px-3.5 py-6 text-center text-gray-400">
              {{ __('Belum ada riwayat shift buka/tutup kasir.') }}
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
