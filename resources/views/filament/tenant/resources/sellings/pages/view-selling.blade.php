@php
  use App\Features\{SellingTax};
  use App\Models\Tenants\{Profile, Setting, About};
@endphp
<x-filament-panels::page>
  <x-filament::section id="printElement">
    <div class="flex">
      <div class="w-full print:w-1/3 md:w-1/3 px-2">
        <div>
          <p class="font-semibold text-2xl">@lang('Selling details')</p>
          <div class="details">
            <ul class="my-1">
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Code')</span><span>#{{ $record->code }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Kategori Pesanan')</span><span>{{ $record->order_type_label }}</span></li>
              @if(About::first() && About::first()->business_type == 'fnb')
                <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Table')</span><span>{{ $record->table?->number ?? 'N/A' }}</span></li>
              @endif
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Cashier')</span><span>{{ $record->user->name }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Date')</span><span>{{ now()->parse($record->date)->setTimezone(Profile::get()->timezone ?? 'UTC')->format('d F Y H:i') }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Payment method')</span><span>{{ $record->paymentMethod->name }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Voucher')</span><span>{{ $record->voucher ?? 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Note Penjualan')</span><span>{{ strip_tags($record->note) ?: '-' }}</span></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="w-full print:w-1/3 md:w-1/3 px-2">
        <div>
          <p class="font-semibold text-2xl">@lang('Member details')</p>
          <div class="details">
            <ul class="my-1">
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Name')</span><span>{{ $record->member?->name ?? 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Code')</span><span>{{ $record->member?->code ?? 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Joined date')</span><span>{{ $record->member?->joined_date ? now()->parse($record->member?->joined_date)->setTimezone(Profile::get()->timezone ?? 'UTC')->format('d F Y H:i') : 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Identity type')</span><span>{{ $record->member?->identity_type ?? 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Identity number')</span><span>{{ $record->member?->identity_number ?? 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Contact')</span><span>{{ $record->member?->email ?? 'N/A' }}</span></li>
              <li class="flex justify-between text-secondary text-sm mb-1"><span class="font-semibold">@lang('Address')</span><span>{{ $record->member?->address ?? 'N/A' }}</span></li>
              <!---->
            </ul>
          </div>
        </div>
      </div>
    </div>
    <div class="table w-full my-4">
      <table class="table ns-table w-full">
        <thead class="text-secondary">
          <tr>
            <th width="400" class="p-2 border">@lang('Product')</th>
            <th width="200" class="p-2 border">@lang('Unit price')</th>
            <th width="200" class="p-2 border">@lang('Quantity')</th>
            <th width="200" class="p-2 border">@lang('Discount')</th>
            <th width="200" class="p-2 border">@lang('Total price')</th>
          </tr>
        </thead>
        <tbody>
          @foreach($record->sellingDetails as $detail)
            <tr>
              <td class="p-2 border">
                <h3 class="text-primary">{{ $detail->product->name }}</h3>
                @if (!empty($detail->selected_variations))
                  <div class="mt-1 space-y-0.5 text-xs text-gray-500">
                    @foreach ($detail->selected_variations as $var)
                      <div>- {{ $var['variation_name'] }}: {{ $var['option_name'] }}
                        @if (!empty($var['price']) && $var['price'] > 0)
                          (+{{ Number::currency($var['price'], Setting::get('currency', 'IDR')) }})
                        @endif
                      </div>
                    @endforeach
                  </div>
                @endif
                @if (!empty($detail->note))
                  <div class="mt-1 text-xs italic text-gray-500">
                    @lang('Note'): {{ $detail->note }}
                  </div>
                @endif
              </td>
              <td class="p-2 border text-center text-primary">{{ Number::currency($detail->price_per_unit, Setting::get('currency', 'IDR')) }}</td>
              <td class="p-2 border text-center text-primary">{{ $detail->qty }}</td>
              <td class="p-2 border text-center text-primary">{{ Number::currency($detail->discount_price, Setting::get('currency', 'IDR')) }}</td>
              <td class="p-2 border text-center text-primary">{{ Number::currency($detail->total_price, Setting::get('currency', 'IDR')) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="font-semibold">
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Subtotal')</td>
            <td class="p-2 border text-right text-primary">{{ Number::currency($record->total_price, Setting::get('currency', 'IDR')) }}</td>
          </tr>
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Discount')</td>
            <td class="p-2 border text-right text-primary">{{ Number::currency($record->total_discount_per_item + $record->discount_price, Setting::get('currency', 'IDR')) }}</td>
          </tr>
          <!---->
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Tax')</td>
            <td class="p-2 border text-right text-primary">{{ $record->tax }}%</td>
          </tr>
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Tax price')</td>
            <td class="p-2 border text-right text-primary">{{ Number::currency($record->tax_price, Setting::get('currency', 'IDR')) }}</td>
          </tr>
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Total')</td>
            <td class="p-2 border text-right text-primary">{{ Number::currency($record->grand_total_price, Setting::get('currency', 'IDR')) }}</td>
          </tr>
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Payed money')</td>
            <td class="p-2 border text-right text-primary">{{ Number::currency($record->payed_money, Setting::get('currency', 'IDR')) }}</td>
          </tr>
          <tr>
            <td class="p-2 border text-center text-primary" colspan="3"></td>
            <td class="p-2 border text-primary text-left">@lang('Money changes')</td>
            <td class="p-2 border text-right text-primary">{{ Number::currency($record->money_changes, Setting::get('currency', 'IDR')) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </x-filament::section>
</x-filament-panels::page>
@script()
<script>
  console.log(@js($record));
  document.getElementById('printInvoice').addEventListener('click', () => {
    const printContents = document.getElementById("printElement").innerHTML;
    const originalContents = document.body.innerHTML;


    document.body.innerHTML = printContents;

    window.print();

    window.location.reload();
  });
  document.getElementById('printButton').addEventListener('click', async () => {
    let selling = @js($record);
    let about = @js($about);
    const printerData = getPrinter();
    const isPrinterActive = printerData && !(printerData instanceof Error) && (printerData.printerId || window.LakasirBridge);
    const paperWidth = (printerData && !(printerData instanceof Error) && printerData.paper_width) ? printerData.paper_width : '58mm';
    const dividerLine = paperWidth === '80mm' ? '------------------------------------------------' : '-------------------------------';

    try {
      if (!isPrinterActive) {
        new FilamentNotification()
          .title('@lang('Printer belum diatur atau tidak aktif')')
          .body('@lang('Struk dialihkan ke format PDF dan dibuka untuk dicetak.')')
          .warning()
          .send();

        window.open('/member/sellings/' + selling.id + '/receipt-pdf?width=' + paperWidth, '_blank');
        return;
      } else {
        const printer = new Printer(printerData.printerId, paperWidth);
        let printerAction = printer.font('a');
        if(about != undefined || about != null) {
          printerAction.size(1)
            .align('center')
            .text(about.shop_name)
            .size(0)
            .text(about.shop_location);
          if(printerData.header != undefined) {
            printerAction
              .text(printerData.header);
          }
          printerAction.align('left')
            .text(dividerLine);
        }
        printerAction.table(['@lang('Cashier')', selling.user.name])
        if(selling.order_type_label != undefined && selling.order_type_label != null && selling.order_type_label != '-') {
          printerAction.table(['@lang('Kategori')', selling.order_type_label])
        }
        if(selling.table != undefined && selling.table != null) {
          printerAction.table(['@lang('Table')', selling.table.number])
        }
        printerAction.table(['@lang('Payment method')', selling.payment_method.name]);
        if(selling.member != undefined && selling.member != null) {
          printerAction
            .table(['Member', selling.member.name]);
        }
        printerAction
          .text(dividerLine);
        selling.selling_details.forEach(sellingDetail => {
          let price = sellingDetail.price;
          let text = moneyFormat(sellingDetail.price / sellingDetail.qty) + ' x ' + sellingDetail.qty.toString();
          printerAction.table([sellingDetail.product.name, moneyFormat(sellingDetail.price / sellingDetail.qty) + ' x ' + sellingDetail.qty.toString()])
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
          .text(dividerLine);
        if("@js(feature(SellingTax::class))" == 'true') {
          printerAction.table(['@lang('Tax')', `${selling.tax}%`])
            .table(['@lang('Tax price')', moneyFormat(selling.tax_price)]);
        }
        printerAction
          .table(['@lang('Subtotal')', moneyFormat(selling.total_price)])
          .table(['@lang('Discount')', `(${moneyFormat(selling.total_discount_per_item + selling.discount_price)})`])
          .table(['@lang('Total price')', moneyFormat(selling.grand_total_price)])
          .text(dividerLine)
          .table(['@lang('Payed money')', moneyFormat(selling.payed_money)])
          .table(['@lang('Change')', moneyFormat(selling.money_changes)])
          .align('center');
        if(printerData.footer != undefined) {
          printerAction
            .text(printerData.footer);
        }
        printerAction.align('left')
          .text('copy');

        const printSuccess = await printerAction
          .cut()
          .print();

        if (!printSuccess) {
          new FilamentNotification()
            .title('@lang('Printer tidak terhubung atau gagal mencetak')')
            .body('@lang('Struk dialihkan ke format PDF.')')
            .warning()
            .send();

          window.open('/member/sellings/' + selling.id + '/receipt-pdf?width=' + paperWidth, '_blank');
        }
      }
    } catch (error) {
      console.error(error);
      new FilamentNotification()
        .title('@lang('Terjadi kendala pada printer fisik')')
        .body('@lang('Struk otomatis dialihkan ke format PDF.')')
        .warning()
        .send();

      window.open('/member/sellings/' + selling.id + '/receipt-pdf?width=' + paperWidth, '_blank');
    }
  });
</script>
@endscript
