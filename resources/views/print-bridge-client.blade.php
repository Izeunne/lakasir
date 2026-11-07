<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Mataverse - Print Bridge Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        body { background: #f8fafc; color: #0f172a; padding: 24px; display: flex; justify-content: center; }
        .card { background: #ffffff; max-width: 620px; width: 100%; border-radius: 16px; border: 1px solid #e2e8f0; padding: 28px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        .header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .badge { background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 9999px; }
        h1 { font-size: 20px; font-weight: 800; color: #4f46e5; }
        p.subtitle { font-size: 13px; color: #64748b; margin-top: 2px; }
        .box { background: #f1f5f9; border-radius: 12px; padding: 16px; margin: 16px 0; border: 1px solid #e2e8f0; }
        .url-box { word-break: break-all; font-family: monospace; font-size: 13px; color: #1e293b; background: #ffffff; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1; margin-top: 8px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; background: #4f46e5; color: white; border: none; padding: 12px 20px; font-size: 14px; font-weight: 600; border-radius: 10px; cursor: pointer; text-decoration: none; width: 100%; margin-top: 10px; transition: background 0.2s; }
        .btn:hover { background: #4338ca; }
        .btn-outline { background: transparent; color: #4f46e5; border: 1.5px solid #4f46e5; margin-top: 8px; }
        .btn-outline:hover { background: #eef2ff; }
        .qr-section { text-align: center; margin: 20px 0; }
        .qr-section img { border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px; background: white; }
        .footer { font-size: 12px; color: #94a3b8; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <span class="badge">POS MATAVERSE x LAKASIR</span>
                <h1>Print Bridge Client Setup</h1>
                <p class="subtitle">Hubungkan aplikasi Android Print Bridge ke server POS Lakasir ini</p>
            </div>
        </div>

        <div class="box">
            <strong style="font-size: 14px;">📋 Link Setup Otomatis:</strong>
            <p style="font-size: 12px; color: #64748b; margin-top: 4px;">Copy link di bawah ini dan paste ke kolom "Setup Cepat" pada aplikasi Android:</p>
            <div class="url-box" id="clientUrl">{{ $fullClientUrl }}</div>
            <button class="btn" onclick="copyUrl()">📋 Salin Link Setup Otomatis</button>
        </div>

        <div class="qr-section">
            <p style="font-size: 13px; font-weight: 600; margin-bottom: 8px;">Scan dengan Kamera / Barcode Scanner di Tablet:</p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($fullClientUrl) }}" alt="QR Setup" width="180" height="180">
        </div>

        <div style="border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <strong style="font-size: 14px;">⚡ Uji Coba Pencetakan:</strong>
            <p style="font-size: 12px; color: #64748b; margin-top: 4px;">Kirim 1 struk pengujian ke antrean untuk memastikan printer merespon.</p>
            <button class="btn btn-outline" onclick="sendTestJob()">🖨️ Kirim Test Print ke Antrean</button>
            <p id="testStatus" style="font-size: 12px; margin-top: 8px; text-align: center;"></p>
        </div>

        <div class="footer">
            POS Mataverse • Lakasir Thermal Print Bridge System &copy; 2026
        </div>
    </div>

    <script>
        function copyUrl() {
            const text = document.getElementById('clientUrl').innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Link setup berhasil disalin ke clipboard! Silakan paste di aplikasi Android Print Bridge.');
            });
        }

        async function sendTestJob() {
            const statusEl = document.getElementById('testStatus');
            statusEl.innerText = 'Mengirim job...';
            statusEl.style.color = '#4f46e5';

            try {
                const response = await fetch('/api/print-queue/test?token={{ $token }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ outlet_id: {{ $outletId }}, type: '{{ $type }}' })
                });
                const data = await response.json();
                if (data.success) {
                    statusEl.innerText = '✅ Berhasil! Job #' + data.job_id + ' masuk ke antrean.';
                    statusEl.style.color = '#10b981';
                } else {
                    statusEl.innerText = '❌ Gagal: ' + (data.message || 'Error');
                    statusEl.style.color = '#ef4444';
                }
            } catch (err) {
                statusEl.innerText = '❌ Error koneksi: ' + err.message;
                statusEl.style.color = '#ef4444';
            }
        }
    </script>
</body>
</html>

