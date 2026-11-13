package id.webprint.bridge.data

import android.content.Context

class SettingsRepository(context: Context) {

    private val preferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    fun load(): BridgeSettings {
        return BridgeSettings(
            clientUrl = preferences.getString(KEY_CLIENT_URL, "").orEmpty(),
            baseUrl = preferences.getString(KEY_BASE_URL, "").orEmpty(),
            token = preferences.getString(KEY_TOKEN, "").orEmpty(),
            deviceId = preferences.getString(KEY_DEVICE_ID, "").orEmpty(),
            outletId = preferences.getInt(KEY_OUTLET_ID, 1),
            queueType = preferences.getString(KEY_QUEUE_TYPE, QueueType.CASHIER.value).orEmpty(),
            stationId = preferences.getString(KEY_STATION_ID, "").orEmpty(),
            paperWidthPreset = preferences.getString(KEY_PAPER_WIDTH_PRESET, "").orEmpty().ifBlank {
                if (preferences.getInt(KEY_PAPER_WIDTH_COLUMNS, 32) >= 48) {
                    PaperWidthPreset.WIDTH_80.value
                } else {
                    PaperWidthPreset.WIDTH_58.value
                }
            },
            paperWidthColumns = preferences.getInt(KEY_PAPER_WIDTH_COLUMNS, 32),
            printerMode = preferences.getString(KEY_PRINTER_MODE, PrinterMode.TCP.value).orEmpty(),
            printerHost = preferences.getString(KEY_PRINTER_HOST, "").orEmpty(),
            printerPort = preferences.getInt(KEY_PRINTER_PORT, 9100),
            bluetoothMacAddress = preferences.getString(KEY_BLUETOOTH_MAC_ADDRESS, "").orEmpty(),
            bluetoothPrinterName = preferences.getString(KEY_BLUETOOTH_PRINTER_NAME, "").orEmpty(),
            bluetoothTransportMode = preferences.getString(
                KEY_BLUETOOTH_TRANSPORT_MODE,
                BluetoothTransportMode.AUTO.value,
            ).orEmpty(),
            pollingSeconds = preferences.getLong(KEY_POLLING_SECONDS, 3L),
            autoStart = preferences.getBoolean(KEY_AUTO_START, false),
            posUrl = preferences.getString(KEY_POS_URL, "").orEmpty(),
            backgroundServerEnabled = preferences.getBoolean(KEY_BACKGROUND_SERVER_ENABLED, false),
            keepScreenOn = preferences.getBoolean(KEY_KEEP_SCREEN_ON, true),
            printers = loadPrinters(preferences),
        )
    }

    private fun loadPrinters(prefs: android.content.SharedPreferences): List<PrinterDevice> {
        val raw = prefs.getString(KEY_PRINTERS_JSON, "").orEmpty()
        val parsed = PrinterDevice.listFromJsonString(raw)
        if (parsed.isNotEmpty()) {
            return parsed
        }
        val legacyHost = prefs.getString(KEY_PRINTER_HOST, "").orEmpty()
        val legacyBt = prefs.getString(KEY_BLUETOOTH_MAC_ADDRESS, "").orEmpty()
        val legacyMode = prefs.getString(KEY_PRINTER_MODE, PrinterMode.TCP.value).orEmpty()
        if (legacyHost.isNotBlank() || legacyBt.isNotBlank()) {
            return listOf(
                PrinterDevice(
                    id = "kasir",
                    name = if (legacyMode == PrinterMode.BLUETOOTH.value) {
                        prefs.getString(KEY_BLUETOOTH_PRINTER_NAME, "").orEmpty().ifBlank { "Printer Kasir" }
                    } else {
                        "Printer Kasir LAN"
                    },
                    mode = legacyMode,
                    host = legacyHost,
                    port = prefs.getInt(KEY_PRINTER_PORT, 9100),
                    bluetoothMacAddress = legacyBt,
                    bluetoothPrinterName = prefs.getString(KEY_BLUETOOTH_PRINTER_NAME, "").orEmpty(),
                    bluetoothTransportMode = prefs.getString(KEY_BLUETOOTH_TRANSPORT_MODE, BluetoothTransportMode.AUTO.value).orEmpty(),
                    paperWidthPreset = prefs.getString(KEY_PAPER_WIDTH_PRESET, PaperWidthPreset.WIDTH_58.value).orEmpty(),
                    paperWidthColumns = prefs.getInt(KEY_PAPER_WIDTH_COLUMNS, 32),
                    isDefault = true,
                ),
            )
        }
        return emptyList()
    }

    fun save(settings: BridgeSettings) {
        preferences.edit()
            .putString(KEY_CLIENT_URL, settings.clientUrl)
            .putString(KEY_BASE_URL, settings.baseUrl)
            .putString(KEY_TOKEN, settings.token)
            .putString(KEY_DEVICE_ID, settings.deviceId)
            .putInt(KEY_OUTLET_ID, settings.outletId)
            .putString(KEY_QUEUE_TYPE, settings.queueType)
            .putString(KEY_STATION_ID, settings.stationId)
            .putString(KEY_PAPER_WIDTH_PRESET, settings.paperWidthPreset)
            .putInt(KEY_PAPER_WIDTH_COLUMNS, settings.paperWidthColumns)
            .putString(KEY_PRINTER_MODE, settings.printerMode)
            .putString(KEY_PRINTER_HOST, settings.printerHost)
            .putInt(KEY_PRINTER_PORT, settings.printerPort)
            .putString(KEY_BLUETOOTH_MAC_ADDRESS, settings.bluetoothMacAddress)
            .putString(KEY_BLUETOOTH_PRINTER_NAME, settings.bluetoothPrinterName)
            .putString(KEY_BLUETOOTH_TRANSPORT_MODE, settings.bluetoothTransportMode)
            .putLong(KEY_POLLING_SECONDS, settings.pollingSeconds)
            .putBoolean(KEY_AUTO_START, settings.autoStart)
            .putString(KEY_POS_URL, settings.posUrl)
            .putBoolean(KEY_BACKGROUND_SERVER_ENABLED, settings.backgroundServerEnabled)
            .putBoolean(KEY_KEEP_SCREEN_ON, settings.keepScreenOn)
            .putString(KEY_PRINTERS_JSON, PrinterDevice.listToJsonString(settings.printers))
            .apply()
    }

    fun validateServer(settings: BridgeSettings): List<String> {
        val errors = mutableListOf<String>()
        if (!settings.baseUrl.startsWith("http://") && !settings.baseUrl.startsWith("https://")) {
            errors += "Base URL harus diawali http:// atau https://"
        }
        if (settings.token.isBlank()) {
            errors += "Token print queue wajib diisi"
        }
        if (settings.outletId <= 0) {
            errors += "Outlet ID wajib lebih dari 0"
        }
        if (QueueType.fromValue(settings.queueType) == QueueType.KITCHEN && settings.stationId.isNotBlank()) {
            settings.stationId.toIntOrNull() ?: run {
                errors += "Station ID harus angka jika diisi"
            }
        }
        if (settings.pollingSeconds < 2) {
            errors += "Polling minimal 2 detik"
        }
        return errors
    }

    fun validatePrinter(settings: BridgeSettings): List<String> {
        val errors = mutableListOf<String>()
        if (settings.printers.isNotEmpty()) {
            settings.printers.forEach { p ->
                if (p.id.isBlank()) {
                    errors += "Alias printer wajib diisi"
                }
                if (p.mode == PrinterMode.TCP.value) {
                    if (p.host.isBlank()) {
                        errors += "IP host untuk printer '${p.name}' wajib diisi"
                    }
                    if (p.port !in 1..65535) {
                        errors += "Port untuk printer '${p.name}' tidak valid"
                    }
                } else if (p.mode == PrinterMode.BLUETOOTH.value) {
                    if (p.bluetoothMacAddress.isBlank()) {
                        errors += "Bluetooth MAC untuk printer '${p.name}' belum dipilih"
                    }
                }
            }
            return errors
        }

        when (PaperWidthPreset.fromValue(settings.paperWidthPreset)) {
            PaperWidthPreset.AUTO,
            PaperWidthPreset.WIDTH_58,
            PaperWidthPreset.WIDTH_80 -> Unit
            PaperWidthPreset.CUSTOM -> {
                if (settings.paperWidthColumns !in 16..64) {
                    errors += "Kolom custom harus di antara 16 sampai 64"
                }
            }
        }
        when (PrinterMode.fromValue(settings.printerMode)) {
            PrinterMode.TCP -> {
                if (settings.printerHost.isBlank()) {
                    errors += "Printer host wajib diisi untuk mode TCP"
                }
                if (settings.printerPort !in 1..65535) {
                    errors += "Printer port tidak valid"
                }
            }
            PrinterMode.BLUETOOTH -> {
                if (settings.bluetoothMacAddress.isBlank()) {
                    errors += "Printer Bluetooth wajib dipilih"
                }
            }
        }
        return errors
    }

    fun validate(settings: BridgeSettings): List<String> {
        return validateServer(settings) + validatePrinter(settings)
    }

    companion object {
        private const val PREFS_NAME = "print_bridge_settings"
        private const val KEY_CLIENT_URL = "client_url"
        private const val KEY_BASE_URL = "base_url"
        private const val KEY_TOKEN = "token"
        private const val KEY_DEVICE_ID = "device_id"
        private const val KEY_OUTLET_ID = "outlet_id"
        private const val KEY_QUEUE_TYPE = "queue_type"
        private const val KEY_STATION_ID = "station_id"
        private const val KEY_PAPER_WIDTH_PRESET = "paper_width_preset"
        private const val KEY_PAPER_WIDTH_COLUMNS = "paper_width_columns"
        private const val KEY_PRINTER_MODE = "printer_mode"
        private const val KEY_PRINTER_HOST = "printer_host"
        private const val KEY_PRINTER_PORT = "printer_port"
        private const val KEY_BLUETOOTH_MAC_ADDRESS = "bluetooth_mac_address"
        private const val KEY_BLUETOOTH_PRINTER_NAME = "bluetooth_printer_name"
        private const val KEY_BLUETOOTH_TRANSPORT_MODE = "bluetooth_transport_mode"
        private const val KEY_POLLING_SECONDS = "polling_seconds"
        private const val KEY_AUTO_START = "auto_start"
        private const val KEY_POS_URL = "pos_url"
        private const val KEY_BACKGROUND_SERVER_ENABLED = "background_server_enabled"
        private const val KEY_KEEP_SCREEN_ON = "keep_screen_on"
        private const val KEY_PRINTERS_JSON = "printers_json"
    }
}
