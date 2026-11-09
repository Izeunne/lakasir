package id.webprint.bridge.bridge

import android.content.Context
import android.os.Handler
import android.os.Looper
import android.util.Base64
import android.webkit.JavascriptInterface
import android.widget.Toast
import id.webprint.bridge.data.PrinterDevice
import id.webprint.bridge.data.SettingsRepository
import id.webprint.bridge.printer.EscPosPrinter
import org.json.JSONArray
import org.json.JSONObject

class LakasirBridgeInterface(
    private val context: Context,
    private val escPosPrinter: EscPosPrinter,
    private val settingsRepository: SettingsRepository,
    private val onOpenSettingsRequested: (() -> Unit)? = null,
    private val onReloadRequested: (() -> Unit)? = null,
) {

    private val mainHandler = Handler(Looper.getMainLooper())

    @JavascriptInterface
    fun isBridgeAvailable(): Boolean {
        return true
    }

    @JavascriptInterface
    fun isPrinterConfigured(): Boolean {
        val settings = settingsRepository.load()
        return settings.defaultPrinter() != null || settings.isReady()
    }

    @JavascriptInterface
    fun printRaw(base64Payload: String, targetPrinter: String? = null): String {
        return try {
            val bytes = Base64.decode(base64Payload, Base64.DEFAULT)
            val settings = settingsRepository.load()
            val targetDevice = settings.findPrinter(targetPrinter)
            if (targetDevice != null) {
                escPosPrinter.printDirect(targetDevice, bytes)
            } else {
                escPosPrinter.printDirect(settings, bytes)
            }
            "SUCCESS"
        } catch (e: Exception) {
            "ERROR: " + (e.message ?: "Gagal mencetak struk")
        }
    }

    @JavascriptInterface
    fun printText(text: String, targetPrinter: String? = null): String {
        return try {
            val bytes = text.toByteArray(Charsets.UTF_8)
            val settings = settingsRepository.load()
            val targetDevice = settings.findPrinter(targetPrinter)
            if (targetDevice != null) {
                escPosPrinter.printDirect(targetDevice, bytes)
            } else {
                escPosPrinter.printDirect(settings, bytes)
            }
            "SUCCESS"
        } catch (e: Exception) {
            "ERROR: " + (e.message ?: "Gagal mencetak teks")
        }
    }

    @JavascriptInterface
    fun openCashDrawer(): String {
        return try {
            val settings = settingsRepository.load()
            escPosPrinter.openDrawer(settings)
            "SUCCESS"
        } catch (e: Exception) {
            "ERROR: " + (e.message ?: "Gagal membuka laci kasir")
        }
    }

    @JavascriptInterface
    fun getDeviceInfo(): String {
        val settings = settingsRepository.load()
        val json = JSONObject()
        json.put("platform", "android")
        json.put("bridge_version", "1.1.0")
        json.put("outlet_id", settings.outletId)

        val printersArray = JSONArray()
        for (printer in settings.printers) {
            val pObj = JSONObject()
            pObj.put("id", printer.id)
            pObj.put("name", printer.name)
            pObj.put("mode", printer.mode)
            pObj.put("is_default", printer.isDefault)
            printersArray.put(pObj)
        }
        json.put("printers", printersArray)
        return json.toString()
    }

    @JavascriptInterface
    fun openSettings() {
        mainHandler.post {
            onOpenSettingsRequested?.invoke()
        }
    }

    @JavascriptInterface
    fun reloadPos() {
        mainHandler.post {
            onReloadRequested?.invoke()
        }
    }

    @JavascriptInterface
    fun toast(message: String) {
        mainHandler.post {
            Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
        }
    }
}

