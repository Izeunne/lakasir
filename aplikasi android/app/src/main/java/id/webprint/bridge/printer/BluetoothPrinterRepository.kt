package id.webprint.bridge.printer

import android.annotation.SuppressLint
import android.bluetooth.BluetoothAdapter
import android.util.Log

class BluetoothPrinterRepository {

    fun isBluetoothAvailable(): Boolean {
        return try {
            BluetoothAdapter.getDefaultAdapter() != null
        } catch (e: Throwable) {
            Log.w("BluetoothRepo", "isBluetoothAvailable failed", e)
            false
        }
    }

    @SuppressLint("MissingPermission")
    fun isBluetoothEnabled(): Boolean {
        return try {
            BluetoothAdapter.getDefaultAdapter()?.isEnabled == true
        } catch (e: Throwable) {
            Log.w("BluetoothRepo", "isBluetoothEnabled failed", e)
            false
        }
    }

    @SuppressLint("MissingPermission")
    fun getBondedPrinters(): List<BluetoothPrinterOption> {
        return try {
            val adapter = BluetoothAdapter.getDefaultAdapter() ?: return emptyList()
            if (!adapter.isEnabled) {
                return emptyList()
            }
            val bonded = adapter.bondedDevices ?: return emptyList()
            bonded.mapNotNull { device ->
                try {
                    val name = device.name?.ifBlank { "Unknown Printer" } ?: "Unknown Printer"
                    val address = device.address.orEmpty()
                    if (address.isBlank()) null else BluetoothPrinterOption(name = name, address = address)
                } catch (e: Throwable) {
                    val address = try { device.address.orEmpty() } catch (_: Throwable) { "" }
                    if (address.isNotBlank()) BluetoothPrinterOption(name = "Printer Bluetooth", address = address) else null
                }
            }.sortedBy { it.name.lowercase() }
        } catch (e: Throwable) {
            Log.w("BluetoothRepo", "getBondedPrinters failed", e)
            emptyList()
        }
    }
}
