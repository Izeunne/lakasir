package id.webprint.bridge.data

data class BridgeSettings(
    val clientUrl: String = "",
    val baseUrl: String = "",
    val token: String = "",
    val deviceId: String = "",
    val outletId: Int = 1,
    val queueType: String = QueueType.CASHIER.value,
    val stationId: String = "",
    val paperWidthPreset: String = PaperWidthPreset.WIDTH_58.value,
    val paperWidthColumns: Int = 32,
    val printerMode: String = PrinterMode.TCP.value,
    val printerHost: String = "",
    val printerPort: Int = 9100,
    val bluetoothMacAddress: String = "",
    val bluetoothPrinterName: String = "",
    val bluetoothTransportMode: String = BluetoothTransportMode.AUTO.value,
    val pollingSeconds: Long = 3L,
    val autoStart: Boolean = false,
    val posUrl: String = "",
    val backgroundServerEnabled: Boolean = false,
    val keepScreenOn: Boolean = true,
    val printers: List<PrinterDevice> = emptyList(),
) {
    fun resolvedPosUrl(): String {
        if (posUrl.isNotBlank()) return posUrl.trim()
        if (baseUrl.isNotBlank()) {
            val clean = baseUrl.trim().trimEnd('/')
            return if (clean.endsWith("/member")) clean else "$clean/member"
        }
        return ""
    }
    fun isReady(): Boolean {
        if (baseUrl.isBlank() || token.isBlank() || outletId <= 0) return false
        if (printers.isNotEmpty()) {
            return printers.any { p ->
                if (p.mode == PrinterMode.BLUETOOTH.value) p.bluetoothMacAddress.isNotBlank()
                else p.host.isNotBlank() && p.port > 0
            }
        }
        return when (printerMode) {
            PrinterMode.BLUETOOTH.value -> bluetoothMacAddress.isNotBlank()
            else -> printerHost.isNotBlank() && printerPort > 0
        }
    }

    fun defaultPrinter(): PrinterDevice? {
        return printers.firstOrNull { it.isDefault } ?: printers.firstOrNull() ?: legacyAsPrinterDevice()
    }

    fun findPrinter(target: String?): PrinterDevice? {
        if (target.isNullOrBlank()) {
            return defaultPrinter()
        }
        return printers.firstOrNull {
            it.id.equals(target, ignoreCase = true) || it.name.equals(target, ignoreCase = true)
        } ?: defaultPrinter()
    }

    fun legacyAsPrinterDevice(): PrinterDevice? {
        val hasBt = bluetoothMacAddress.isNotBlank()
        val hasTcp = printerHost.isNotBlank() && printerPort > 0
        if (!hasBt && !hasTcp) return null

        return PrinterDevice(
            id = "default",
            name = if (printerMode == PrinterMode.BLUETOOTH.value) {
                bluetoothPrinterName.ifBlank { "Printer Bluetooth" }
            } else {
                "Printer LAN ($printerHost)"
            },
            mode = printerMode,
            host = printerHost,
            port = printerPort,
            bluetoothMacAddress = bluetoothMacAddress,
            bluetoothPrinterName = bluetoothPrinterName,
            bluetoothTransportMode = bluetoothTransportMode,
            paperWidthPreset = paperWidthPreset,
            paperWidthColumns = paperWidthColumns,
            isDefault = true,
        )
    }

    fun resolvedPaperWidthColumns(): Int {
        return when (PaperWidthPreset.fromValue(paperWidthPreset)) {
            PaperWidthPreset.AUTO -> paperWidthColumns.takeIf { it > 0 } ?: 32
            PaperWidthPreset.WIDTH_58 -> 32
            PaperWidthPreset.WIDTH_80 -> 48
            PaperWidthPreset.CUSTOM -> paperWidthColumns
        }
    }
}

enum class PaperWidthPreset(val value: String) {
    AUTO("auto"),
    WIDTH_58("58mm"),
    WIDTH_80("80mm"),
    CUSTOM("custom");

    companion object {
        fun fromValue(value: String?): PaperWidthPreset {
            return entries.firstOrNull { it.value == value } ?: WIDTH_58
        }
    }
}

enum class QueueType(val value: String) {
    CASHIER("cashier"),
    KITCHEN("kitchen");

    companion object {
        fun fromValue(value: String?): QueueType {
            return entries.firstOrNull { it.value == value } ?: CASHIER
        }
    }
}

enum class PrinterMode(val value: String) {
    TCP("tcp"),
    BLUETOOTH("bluetooth");

    companion object {
        fun fromValue(value: String?): PrinterMode {
            return entries.firstOrNull { it.value == value } ?: TCP
        }
    }
}

enum class BluetoothTransportMode(val value: String) {
    AUTO("auto"),
    CLASSIC("classic"),
    BLE("ble");

    companion object {
        fun fromValue(value: String?): BluetoothTransportMode {
            return entries.firstOrNull { it.value == value } ?: AUTO
        }
    }
}
