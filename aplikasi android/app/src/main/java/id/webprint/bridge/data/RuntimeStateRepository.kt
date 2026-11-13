package id.webprint.bridge.data

import android.content.Context
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.atomic.AtomicReference

data class RuntimeState(
    val isPolling: Boolean = false,
    val printedCount: Int = 0,
    val failedCount: Int = 0,
    val pollsCount: Int = 0,
    val lastStatus: String = "Siap digunakan. Isi konfigurasi lalu klik Mulai.",
    val logs: List<String> = listOf("Siap digunakan. Isi konfigurasi lalu klik Mulai."),
)

class RuntimeStateRepository(context: Context) {

    private val timeFormatter = SimpleDateFormat("HH.mm.ss", Locale("id", "ID"))

    fun load(): RuntimeState {
        return inMemoryState.get()
    }

    fun reset() {
        inMemoryState.set(
            RuntimeState(
                isPolling = false,
                printedCount = 0,
                failedCount = 0,
                pollsCount = 0,
                lastStatus = DEFAULT_LOG,
                logs = listOf(DEFAULT_LOG),
            ),
        )
    }

    fun clearLogs() {
        inMemoryState.updateAndGet { state ->
            state.copy(logs = listOf("Log aktivitas dibersihkan."))
        }
    }

    fun setPolling(active: Boolean, message: String) {
        inMemoryState.updateAndGet { state ->
            state.copy(
                isPolling = active,
                lastStatus = message,
            )
        }
    }

    fun registerPoll(message: String) {
        // High-frequency in-memory update without any disk I/O thrashing
        inMemoryState.updateAndGet { state ->
            state.copy(
                isPolling = true,
                pollsCount = state.pollsCount + 1,
                lastStatus = message,
            )
        }
    }

    fun registerPrinted(message: String) {
        inMemoryState.updateAndGet { state ->
            state.copy(
                printedCount = state.printedCount + 1,
                lastStatus = message,
                logs = addLog(state.logs, decorate(message)),
            )
        }
    }

    fun registerFailed(message: String) {
        inMemoryState.updateAndGet { state ->
            state.copy(
                failedCount = state.failedCount + 1,
                lastStatus = message,
                logs = addLog(state.logs, decorate(message)),
            )
        }
    }

    fun appendLog(message: String) {
        inMemoryState.updateAndGet { state ->
            state.copy(
                lastStatus = message,
                logs = addLog(state.logs, decorate(message)),
            )
        }
    }

    fun updateStatus(message: String) {
        inMemoryState.updateAndGet { state ->
            state.copy(
                lastStatus = message,
            )
        }
    }

    private fun decorate(message: String): String {
        return "[${timeFormatter.format(Date())}] $message"
    }

    private fun addLog(existing: List<String>, entry: String): List<String> {
        return (listOf(entry) + existing).take(MAX_LOGS)
    }

    companion object {
        private const val MAX_LOGS = 10
        private const val DEFAULT_LOG = "Siap digunakan. Isi konfigurasi lalu klik Mulai."

        // Shared in-memory state across MainActivity and BridgeForegroundService within the same process
        private val inMemoryState = AtomicReference(
            RuntimeState(
                isPolling = false,
                printedCount = 0,
                failedCount = 0,
                pollsCount = 0,
                lastStatus = DEFAULT_LOG,
                logs = listOf(DEFAULT_LOG),
            ),
        )
    }
}
