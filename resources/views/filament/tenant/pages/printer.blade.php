<x-filament-panels::page>
  <!-- Android Print Bridge Detected Card (Dual-Mode Native) -->
  <div id="lakasirBridgeBanner" style="display: none; background: #ecfdf5; border: 1.5px solid #10b981; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <div>
        <div style="font-size: 16px; font-weight: 700; color: #065f46; display: flex; align-items: center; gap: 6px;">
          <span>📱</span> Lakasir Android Bridge (Dual-Mode Aktif)
          <span style="background: #10b981; color: white; font-size: 11px; padding: 2px 8px; border-radius: 9999px;">Direct Native</span>
        </div>
        <p style="font-size: 13px; color: #047857; margin-top: 4px;">
          POS sedang berjalan di aplikasi Android Lakasir. Pencetakan struk dan pembukaan laci kasir langsung diproses oleh driver Bluetooth/LAN di HP/tablet ini.
        </p>
      </div>
      <button type="button" onclick="if (window.LakasirBridge) window.LakasirBridge.openSettings();" style="background: #059669; color: white; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
        <span>⚙️</span> Atur Printer di Android
      </button>
    </div>
  </div>

  <!-- Android Print Bridge Quick Card (Untuk Browser Luar) -->
  <div id="standardBridgeBanner" style="background: #eef2ff; border: 1.5px solid #6366f1; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
      <div>
        <div style="font-size: 16px; font-weight: 700; color: #4338ca; display: flex; align-items: center; gap: 6px;">
          <span>📱</span> POS Mataverse • Android Print Bridge
          <span style="background: #10b981; color: white; font-size: 11px; padding: 2px 8px; border-radius: 9999px;">Tersedia</span>
        </div>
        <p style="font-size: 13px; color: #4b5563; margin-top: 4px;">
          Mencetak struk kasir &amp; dapur secara otomatis tanpa dialog popup menggunakan aplikasi Android Print Bridge (Bluetooth / TCP LAN).
        </p>
      </div>
      <a href="/print-client.html" target="_blank" style="background: #4f46e5; color: white; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
        <span>⚡</span> Buka Setup Android Bridge &amp; QR
      </a>
    </div>
  </div>

  <div x-data="printer">
    <x-filament-panels::form
      x-ref="printerForm"
      id="form"
      wire:key="{{ 'forms.' . $this->getFormStatePath() }}">
      {{ $this->form }}

    <x-filament-panels::form.actions
      :actions="$this->getCachedFormActions()"
      :full-width="$this->hasFullWidthFormActions()"
      />
    </x-filament-panels::form>
  </div>
</x-filament-panels::page>
@script()
  <script>
    Alpine.data('printer', () => ({
      init() {
        if (window.LakasirBridge) {
          const lakasirBanner = document.getElementById('lakasirBridgeBanner');
          const stdBanner = document.getElementById('standardBridgeBanner');
          if (lakasirBanner) lakasirBanner.style.display = 'block';
          if (stdBanner) stdBanner.style.display = 'none';

          if (!$wire.data.name) {
            $wire.data.name = 'Lakasir POS Native';
            $wire.data.driver = 'bluetooth';
            $wire.data.printer = 'Android Printer (Dual-Mode)';
            $wire.data.printerId = 'android_bridge_default';
          }
        }
        if(localStorage.printer) {
          const printer = JSON.parse(localStorage.printer);
          $wire.data = {
            ...printer
          }
        }
      },
      fetchDeviceByDriver() {
        if (window.LakasirBridge) {
          window.LakasirBridge.openSettings();
          return;
        }
        if($wire.data.driver == 'bluetooth') {
          this.fetchBluetooth();
        }
        if($wire.data.driver == 'usb') {
          this.fetchTheUsb();
        }
      },
      async fetchTheUsb() {
        let selectedDevice = null;
        try {
          selectedDevice = await navigator.usb.requestDevice({ filters: [] });
          await selectedDevice.open();
          await selectedDevice.selectConfiguration(1);
          await selectedDevice.claimInterface(0);
          $wire.data.printer = selectedDevice.productName;
          $wire.data.printerId = selectedDevice.vendorId;
          console.log('USB printer selected:', selectedDevice.productName);
        } catch (error) {
          console.error(error);
        }
      },
      async fetchBluetooth() {
        if (window.LakasirBridge) {
          window.LakasirBridge.openSettings();
        }
      },
      save() {
        if (!window.LakasirBridge) {
          $wire.validateInput();
          if(!$wire.data.printer || !$wire.data.name) {
            return;
          }
        }
        localStorage.setItem("printer", JSON.stringify({
          ...$wire.data,
        }))

        new FilamentNotification()
          .title('@lang('Save success')')
          .success()
          .send()
      },
      async test() {
        if (!window.LakasirBridge) {
          $wire.validateInput();
          if(!$wire.data.printer || !$wire.data.name) {
            return;
          }
        }
        try {
          const printer = new Printer($wire.data.printerId || 'android_bridge_default');
          printerAction = printer.font('a')
            .size(1)
            .align('center')
            .text('Lakasir POS')
            .size(0)
            .text('Test Cetak Dual-Mode');

          if($wire.data.header != undefined) {
            printerAction
              .text($wire.data.header);
          }

          printerAction.align('left')
            .text('-------------------------------')
            .table(['Status Bridge', 'TERHUBUNG'])
            .table(['Metode Cetak', window.LakasirBridge ? 'Direct Native' : 'WebUSB'])
            .table(['Waktu', new Date().toLocaleTimeString()])
            .text('-------------------------------')
            .tableCustom([
              { text: 'Item Uji Coba'},
              { text: '1 x 10.000', style: 'B'}
            ])
            .align('right')
            .text('10.000')
            .text('-------------------------------')
            .tableCustom([
              { text: 'Total', style: 'B'},
              { text: '10.000', style: 'B'}
            ])
            .newLine()
            .align('center');
          if($wire.data.footer != undefined) {
            printerAction
              .text($wire.data.footer);
          }
          await printerAction.cut()
            .print('kasir');

          new FilamentNotification()
            .title('Perintah cetak berhasil dikirim')
            .success()
            .send();
        } catch (e) {
          console.error(e);
          new FilamentNotification()
            .title('Gagal mengirim test cetak: ' + e.message)
            .danger()
            .send();
        }
      }
    }))
  </script>
@endscript
