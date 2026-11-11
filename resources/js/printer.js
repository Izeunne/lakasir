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

    // 0. VIRTUAL / SIMULATOR MODE:
    const currentPrinter = typeof getPrinter === 'function' ? getPrinter() : null;
    const isVirtual = this.printerId === 'virtual_simulator' ||
                      (currentPrinter && !(currentPrinter instanceof Error) && currentPrinter.driver === 'virtual');

    if (isVirtual) {
      console.log('Mencetak ke Virtual Thermal Simulator...');
      this.renderVirtualReceipt(commands);
      this.clearCommands();
      if (typeof FilamentNotification !== 'undefined') {
        new FilamentNotification()
          .title('Simulasi Cetak Struk (' + this.paperWidth + ')')
          .body('Struk berhasil ditampilkan pada Virtual Printer Simulator.')
          .success()
          .send();
      }
      return true;
    }

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

  renderVirtualReceipt(rawCommands) {
    const parsedLines = this.parseEscPos(rawCommands);

    let modal = document.getElementById('lakasir-virtual-printer-modal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'lakasir-virtual-printer-modal';
      document.body.appendChild(modal);
    }

    const is80mm = this.paperWidth === '80mm';
    const paperWidthPx = is80mm ? '380px' : '290px';
    const fontSize = is80mm ? '12.5px' : '11.5px';
    const charCols = is80mm ? 48 : 32;

    let receiptContentHtml = '';
    let plainText = '';

    parsedLines.forEach(item => {
      plainText += item.rawText + '\n';

      if (item.isDivider) {
        receiptContentHtml += `
          <div style="border-top: 1px dashed #64748b; margin: 6px 0;"></div>
        `;
        return;
      }

      if (!item.text.trim()) {
        receiptContentHtml += `<div style="height: 8px;"></div>`;
        return;
      }

      const textAlign = item.align;
      const fontWeight = item.bold || item.doubleSize ? 'bold' : 'normal';
      const itemFontSize = item.doubleSize ? (is80mm ? '16px' : '14px') : fontSize;
      const letterSpacing = item.doubleSize ? '0.5px' : 'normal';
      const textTransform = item.doubleSize ? 'uppercase' : 'none';

      receiptContentHtml += `
        <div style="
          text-align: ${textAlign};
          font-weight: ${fontWeight};
          font-size: ${itemFontSize};
          letter-spacing: ${letterSpacing};
          text-transform: ${textTransform};
          line-height: 1.35;
          word-break: break-word;
          white-space: pre-wrap;
          font-family: 'Courier New', Courier, 'Lucida Console', monospace;
          color: #0f172a;
        ">${this.escapeHtml(item.text)}</div>
      `;
    });

    modal.innerHTML = `
      <div style="
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(4px);
        padding: 16px;
        overflow-y: auto;
      " onclick="if(event.target === this) window.closeVirtualReceiptSimulator();">
        <div style="
          background: #ffffff;
          border-radius: 16px;
          box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
          width: 100%;
          max-width: ${is80mm ? '480px' : '400px'};
          max-height: 90vh;
          display: flex;
          flex-direction: column;
          overflow: hidden;
        ">
          <!-- Header Modal -->
          <div style="
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
          ">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-size: 20px;">🖨️</span>
              <div>
                <div style="font-size: 14px; font-weight: 700; color: #0f172a;">Virtual Thermal Simulator</div>
                <div style="font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 6px;">
                  <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #10b981;"></span>
                  <span>Kertas ${this.paperWidth} (${charCols} karakter/baris)</span>
                </div>
              </div>
            </div>
            <button type="button" onclick="window.closeVirtualReceiptSimulator();" style="
              background: #e2e8f0;
              border: none;
              color: #475569;
              width: 28px;
              height: 28px;
              border-radius: 50%;
              cursor: pointer;
              font-size: 16px;
              line-height: 1;
              display: flex;
              align-items: center;
              justify-content: center;
            ">&times;</button>
          </div>

          <!-- Body Scrollable with Realistic Thermal Paper -->
          <div style="
            padding: 20px 16px;
            background: #f1f5f9;
            overflow-y: auto;
            display: flex;
            justify-content: center;
            flex: 1;
          ">
            <div id="virtualReceiptPaper" style="
              background: #ffffff;
              width: ${paperWidthPx};
              box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
              padding: 24px 14px 28px 14px;
              position: relative;
              font-family: 'Courier New', Courier, 'Lucida Console', monospace;
              border-top: 3px dashed #94a3b8;
              border-bottom: 3px dashed #94a3b8;
            ">
              ${receiptContentHtml}
            </div>
          </div>

          <!-- Footer Actions -->
          <div style="
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 12px 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: space-between;
          ">
            <button type="button" onclick="window.copyVirtualReceiptText();" style="
              background: #f1f5f9;
              border: 1px solid #cbd5e1;
              color: #334155;
              padding: 8px 12px;
              border-radius: 8px;
              font-size: 12px;
              font-weight: 600;
              cursor: pointer;
              display: inline-flex;
              align-items: center;
              gap: 4px;
            ">
              📋 Salin Teks
            </button>
            <button type="button" onclick="window.printVirtualReceiptPaper();" style="
              background: #0284c7;
              border: none;
              color: #ffffff;
              padding: 8px 14px;
              border-radius: 8px;
              font-size: 12px;
              font-weight: 600;
              cursor: pointer;
              display: inline-flex;
              align-items: center;
              gap: 4px;
            ">
              🖨️ Cetak (Browser)
            </button>
            <button type="button" onclick="window.closeVirtualReceiptSimulator();" style="
              background: #e2e8f0;
              border: none;
              color: #1e293b;
              padding: 8px 14px;
              border-radius: 8px;
              font-size: 12px;
              font-weight: 600;
              cursor: pointer;
            ">
              Tutup
            </button>
          </div>
        </div>
      </div>
    `;

    window._lastVirtualReceiptText = plainText;
  }

  parseEscPos(commands) {
    const lines = (commands || '').split(/\r?\n|\x0A/);
    const parsed = [];

    let currentAlign = 'left';
    let isBold = false;
    let isDoubleSize = false;

    lines.forEach(rawLine => {
      let line = rawLine;

      if (line.includes('\x1b\x61\x01')) {
        currentAlign = 'center';
      } else if (line.includes('\x1b\x61\x02')) {
        currentAlign = 'right';
      } else if (line.includes('\x1b\x61\x00')) {
        currentAlign = 'left';
      }

      if (line.includes('\x1d\x21') && !line.includes('\x1d\x21\x00')) {
        isDoubleSize = true;
      } else if (line.includes('\x1d\x21\x00')) {
        isDoubleSize = false;
      }

      if (line.includes('\x1b\x45\x01')) {
        isBold = true;
      } else if (line.includes('\x1b\x45\x00')) {
        isBold = false;
      }

      let cleanText = line
        .replace(/\x1b[\x00-\x7f][\x00-\x7f]?/g, '')
        .replace(/\x1d[\x00-\x7f][\x00-\x7f]?/g, '')
        .replace(/[\x00-\x09\x0b-\x1f\x7f]/g, '');

      const isDivider = /^[-=]{15,}$/.test(cleanText.trim());

      parsed.push({
        text: cleanText,
        rawText: cleanText,
        align: currentAlign,
        bold: isBold,
        doubleSize: isDoubleSize,
        isDivider: isDivider,
      });

      if (line.includes('\x1d\x21\x00')) {
        isDoubleSize = false;
      }
    });

    return parsed;
  }

  escapeHtml(str) {
    return (str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}

// Global Virtual Printer Helpers
window.closeVirtualReceiptSimulator = function() {
  const modal = document.getElementById('lakasir-virtual-printer-modal');
  if (modal) {
    modal.innerHTML = '';
  }
};

window.copyVirtualReceiptText = function() {
  if (navigator.clipboard && window._lastVirtualReceiptText) {
    navigator.clipboard.writeText(window._lastVirtualReceiptText);
    if (typeof FilamentNotification !== 'undefined') {
      new FilamentNotification()
        .title('Teks Struk Disalin')
        .body('Teks struk berhasil disalin ke clipboard.')
        .success()
        .send();
    }
  }
};

window.printVirtualReceiptPaper = function() {
  const paper = document.getElementById('virtualReceiptPaper');
  if (!paper) return;

  const printWindow = window.open('', '_blank', 'width=450,height=600');
  if (!printWindow) return;

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>Cetak Struk Virtual</title>
        <style>
          @page { margin: 4mm; }
          body {
            margin: 0;
            padding: 10px;
            font-family: 'Courier New', Courier, monospace;
            background: #fff;
            color: #000;
          }
        </style>
      </head>
      <body>
        ${paper.innerHTML}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 300);
};

