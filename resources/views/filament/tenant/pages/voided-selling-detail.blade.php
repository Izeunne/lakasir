<div class="space-y-4 text-sm">
  <div class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-4">
    <div>
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Kode Transaksi') }}</p>
      <p class="font-semibold text-gray-900 dark:text-white">{{ $selling->code }}</p>
    </div>
    <div>
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Kasir') }}</p>
      <p class="font-semibold text-gray-900 dark:text-white">{{ $selling->user?->name ?? '-' }}</p>
    </div>
    <div>
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Di-void Oleh') }}</p>
      <p class="font-semibold text-gray-900 dark:text-white">{{ $selling->voidedBy?->name ?? '-' }}</p>
    </div>
    <div>
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Waktu Void') }}</p>
      <p class="font-semibold text-gray-900 dark:text-white">{{ $selling->deleted_at?->format('d M Y H:i:s') ?? '-' }}</p>
    </div>
    <div class="col-span-2">
      <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Alasan Void') }}</p>
      <p class="font-medium text-gray-900 dark:text-white">{{ $selling->void_reason ?: '-' }}</p>
    </div>
  </div>

  <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
    <table class="w-full text-left text-xs">
      <thead class="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="px-3 py-2 font-semibold">{{ __('Produk') }}</th>
          <th class="px-3 py-2 font-semibold text-right">{{ __('Qty') }}</th>
          <th class="px-3 py-2 font-semibold text-right">{{ __('Harga') }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach ($selling->sellingDetails as $detail)
          <tr>
            <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $detail->product?->name ?? '-' }}</td>
            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $detail->qty }}</td>
            <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">{{ price_format($detail->price) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot class="bg-gray-50 dark:bg-gray-800/40 font-bold">
        <tr>
          <td colspan="2" class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ __('Total') }}</td>
          <td class="px-3 py-2 text-right text-gray-900 dark:text-white">{{ price_format($selling->total_price) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
