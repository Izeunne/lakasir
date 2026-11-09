package id.webprint.bridge.data

import org.json.JSONArray
import org.json.JSONObject

data class PrinterDevice(
    val id: String, // Unique alias, e.g. "kasir", "dapur", "bar"
    val name: String, // Display label, e.g. "Printer Kasir", "Printer Dapur"
    val mode: String = PrinterMode.BLUETOOTH.value, // "bluetooth" or "tcp"
    val host: String = "",
    val port: Int = 9100,
    val bluetoothMacAddress: String = "",
    val bluetoothPrinterName: String = "",
    val bluetoothTransportMode: String = BluetoothTransportMode.AUTO.value,
    val paperWidthPreset: String = PaperWidthPreset.WIDTH_58.value,
    val paperWidthColumns: Int = 32,
    val isDefault: Boolean = false,
) {
    fun summary(): String {
        val type = if (mode == PrinterMode.TCP.value) {
            "TCP $host:$port"
        } else {
            "BT ${bluetoothPrinterName.ifBlank { bluetoothMacAddress }}"
        }
        val def = if (isDefault) " [Default]" else ""
        return "$name ($id)$def • $type • $paperWidthPreset"
    }

    fun toJson(): JSONObject {
        return JSONObject().apply {
            put("id", id)
            put("name", name)
            put("mode", mode)
            put("host", host)
            put("port", port)
            put("bluetoothMacAddress", bluetoothMacAddress)
            put("bluetoothPrinterName", bluetoothPrinterName)
            put("bluetoothTransportMode", bluetoothTransportMode)
            put("paperWidthPreset", paperWidthPreset)
            put("paperWidthColumns", paperWidthColumns)
            put("isDefault", isDefault)
        }
    }

    companion object {
        fun fromJson(json: JSONObject): PrinterDevice {
            return PrinterDevice(
                id = json.optString("id").ifBlank { "printer_${System.currentTimeMillis() % 1000}" },
                name = json.optString("name").ifBlank { "Printer" },
                mode = json.optString("mode", PrinterMode.BLUETOOTH.value),
                host = json.optString("host", ""),
                port = json.optInt("port", 9100),
                bluetoothMacAddress = json.optString("bluetoothMacAddress", ""),
                bluetoothPrinterName = json.optString("bluetoothPrinterName", ""),
                bluetoothTransportMode = json.optString("bluetoothTransportMode", BluetoothTransportMode.AUTO.value),
                paperWidthPreset = json.optString("paperWidthPreset", PaperWidthPreset.WIDTH_58.value),
                paperWidthColumns = json.optInt("paperWidthColumns", 32),
                isDefault = json.optBoolean("isDefault", false),
            )
        }

        fun listToJsonString(list: List<PrinterDevice>): String {
            val arr = JSONArray()
            list.forEach { arr.put(it.toJson()) }
            return arr.toString()
        }

        fun listFromJsonString(jsonStr: String): List<PrinterDevice> {
            if (jsonStr.isBlank()) return emptyList()
            return runCatching {
                val arr = JSONArray(jsonStr)
                List(arr.length()) { i -> fromJson(arr.getJSONObject(i)) }
            }.getOrDefault(emptyList())
        }
    }
}

