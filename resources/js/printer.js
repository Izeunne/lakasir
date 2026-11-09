class Printer {
  constructor(printerId, paperWidth = '58mm') {
    this.commands = '';
    this.paperWidth = paperWidth;
    this.lineWidth = paperWidth === '80mm' ? 48 : 32;
    this.printerId = printerId;
  }

  async print(targetPrinter = 'kasir') {
    const commands = this.getCommands();
    console.log('Mengirim perintah cetak ESC/POS...');

    // 1. DUAL-MODE / HYBRID: Jika berjalan di dalam aplikasi Android Lakasir (WebView)
    if (window.LakasirBridge && typeof window.LakasirBridge.printRaw === 'function') {
      try {
        const bytes = new Uint8Array(commands.length);
        for (let i = 0; i < commands.length; i++) {
          bytes[i] = commands.charCodeAt(i) & 0xff;
        }
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
          binary += String.fromCharCode(bytes[i]);
        }
        const base64 = window.btoa(binary);

        const result = window.LakasirBridge.printRaw(base64, targetPrinter);
        if (result === 'SUCCESS') {
          console.log('Struk berhasil dicetak langsung via LakasirBridge Android');
          this.clearCommands();
          return true;
        } else {
          console.error('LakasirBridge error:', result);
          if (typeof FilamentNotification !== 'undefined') {
            new FilamentNotification()
              .title('Gagal cetak via Android Bridge: ' + result)
              .danger()
              .send();
          }
          return false;
        }
      } catch (err) {
        console.error('Error saat cetak via LakasirBridge:', err);
      }
    }

    // 2. FALLBACK: WebUSB jika dibuka di Chrome Desktop/Laptop
    try {
      if (!navigator.usb) {
        console.warn('WebUSB tidak didukung di browser ini.');
        if (typeof FilamentNotification !== 'undefined') {
          new FilamentNotification()
            .title('Perangkat/browser tidak mendukung cetak USB. Silakan gunakan Aplikasi Android Lakasir.')
            .danger()
            .send();
        }
        return false;
      }

      const devices = await navigator.usb.getDevices();
      const device = devices.find(device => device.vendorId === this.printerId);
      if (device) {
        await device.open();
        await device.selectConfiguration(1);
        await device.claimInterface(0);

        const encoder = new TextEncoder();
        const data = encoder.encode(commands);
        const endpoint = device.configuration.interfaces[0].alternate.endpoints.filter(endpoint => endpoint.direction === 'out')[0]
        await device.transferOut(endpoint.endpointNumber, data);
        await device.close();
        console.log('Data sent to printer via WebUSB');
        this.clearCommands();
        return true;
      } else {
        console.log('No USB device with the specified vendor ID found');
        if (typeof FilamentNotification !== 'undefined') {
          new FilamentNotification()
            .title('You should choose the printer first in printer setting')
            .danger()
            .actions([
              new FilamentNotificationAction('Setting')
                .icon('heroicon-o-cog-6-tooth')
                .button()
                .url('/member/printer'),
            ])
            .send()
        }
        return false;
      }
    } catch (e) {
      console.error(e);
      return false;
    }
  }

  addCommand(command) {
    this.commands += command;
  }

  font(font) {
    const fonts = {
      'a': '\x1b\x4d\x00', // Font A
      'b': '\x1b\x4d\x01'  // Font B
    };
    this.addCommand(fonts[font]);
    return this;
  }

  align(align) {
    const alignments = {
      'left': '\x1b\x61\x00',
      'center': '\x1b\x61\x01',
      'right': '\x1b\x61\x02'
    };
    this.addCommand(alignments[align]);
    return this;
  }

  style(style) {
    const styles = {
      'bold': '\x1b\x45\x01',
      'underline': '\x1b\x2d\x01',
      'normal': '\x1b\x45\x00' + '\x1b\x2d\x00'
    };
    this.addCommand(styles[style ?? 'normal']);
    return this;
  }

  size(width, height) {
    this.addCommand('\x1d\x21' + String.fromCharCode((width << 4) | height));
    return this;
  }

  text(text) {
    this.addCommand(text + '\n');
    return this;
  }

  barcode(code, type) {
    const types = {
      'EAN8': '\x1d\x6b\x02'
    };
    this.addCommand(types[type] + code + '\x00');
    return this;
  }

  table(data) {
    let row = '';
    const totalTextLength = data.reduce((sum, text) => sum + text.length, 0);

    const totalPadding = this.lineWidth - totalTextLength;
    data.forEach((text, index) => {
      row += text;
      if (index < data.length) {
        for (let i = 0; i < totalPadding; i++) {
          row += ' ';
        }
      }
    });

    this.addCommand(row.trim() + '\x0A');
    return this;
  }

  tableCustom(data) {
    const totalTextLength = data.reduce((sum, text) => sum + text.text.length, 0);

    const totalPadding = this.lineWidth - totalTextLength;
    let row = '';

    data.forEach((cell, index) => {
      let style = cell.style === 'B' ? '\x1b\x45\x01' : '\x1b\x45\x00';

      row += style + cell.text;
      if (index < data.length) {
        for (let i = 0; i < totalPadding; i++) {
          row += ' ';
        }
      }
    });

    this.addCommand(row.trim() + '\x0A');
    return this;
  }

  newLine(line = 1) {
    for (let i = 0; i < line; i++) {
      this.text('\n');
    }

    return this;
  }

  cut() {
    this.addCommand('\n\n' + '\x1d\x56\x00');
    return this;
  }

  getCommands() {
    return this.commands;
  }

  clearCommands() {
    this.commands = '';
  }
}

