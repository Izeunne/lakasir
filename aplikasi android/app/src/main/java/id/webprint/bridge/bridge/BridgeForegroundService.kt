package id.webprint.bridge.bridge

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import id.webprint.bridge.MainActivity
import id.webprint.bridge.R
import id.webprint.bridge.data.PrintJobRepository
import id.webprint.bridge.data.RuntimeStateRepository
import id.webprint.bridge.data.SettingsRepository
import id.webprint.bridge.printer.EscPosPrinter
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

class BridgeForegroundService : Service() {

    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private lateinit var settingsRepository: SettingsRepository
    private lateinit var runtimeStateRepository: RuntimeStateRepository
    private lateinit var printJobRepository: PrintJobRepository
    private lateinit var printer: EscPosPrinter
    private var loopJob: Job? = null

    override fun onCreate() {
        super.onCreate()
        isRunning = true
        settingsRepository = SettingsRepository(this)
        runtimeStateRepository = RuntimeStateRepository(this)
        printJobRepository = PrintJobRepository(settingsRepository)
        printer = EscPosPrinter(this)
        createNotificationChannel()
        startForeground(NOTIFICATION_ID, buildNotification(getString(R.string.notification_idle)))
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        isRunning = true
        if (loopJob?.isActive == true) {
            runtimeStateRepository.updateStatus("Polling sudah aktif.")
            return START_STICKY
        }

        runtimeStateRepository.setPolling(true, "Polling aktif.")
        loopJob = serviceScope.launch {
            while (isActive) {
                val settings = settingsRepository.load()
                if (!settings.isReady()) {
                    updateNotification(getString(R.string.notification_waiting_config))
                    runtimeStateRepository.updateStatus("Konfigurasi belum lengkap.")
                    delay(5_000)
                    continue
                }

                try {
                    val pollUrl = printJobRepository.getPollUrl(settings)
                    updateNotification(getString(R.string.notification_polling, settings.baseUrl))
                    runtimeStateRepository.registerPoll("Polling dimulai: $pollUrl")
                    val jobs = printJobRepository.fetchJobs(settings)
                    if (jobs.isEmpty()) {
                        delay(settings.pollingSeconds.coerceAtLeast(2L) * 1_000L)
                        continue
                    }

                    runtimeStateRepository.appendLog("📥 ${jobs.size} job baru diterima")
                    for (job in jobs) {
                        if (!isActive) break

                        val targetDevice = settings.findPrinter(job.targetPrinter)
                        val printerLabel = if (targetDevice != null) {
                            "'${targetDevice.name}' (${targetDevice.id})"
                        } else {
                            "Default Printer"
                        }
                        val targetTag = if (!job.targetPrinter.isNullOrBlank()) " [Target: ${job.targetPrinter}]" else ""

                        updateNotification(getString(R.string.notification_printing, job.id))
                        runtimeStateRepository.appendLog(
                            "🖨️ Mencetak job #${job.id}$targetTag -> $printerLabel",
                        )

                        try {
                            if (targetDevice != null) {
                                printer.print(job, targetDevice)
                            } else {
                                printer.print(job, settings)
                            }
                            printJobRepository.markComplete(settings, job.id)
                            runtimeStateRepository.registerPrinted("✅ Job #${job.id} sukses dicetak ke $printerLabel")
                            updateNotification(getString(R.string.notification_success, job.id))
                        } catch (printException: Exception) {
                            if (printException is CancellationException) {
                                throw printException
                            }
                            val printError = printException.message ?: printException.javaClass.simpleName
                            updateNotification(getString(R.string.notification_error, printError))
                            runtimeStateRepository.registerFailed("❌ Error cetak job #${job.id} ($printerLabel): $printError")
                            runCatching {
                                printJobRepository.markFailed(settings, job.id, printError)
                            }
                        }
                    }
                } catch (exception: Exception) {
                    if (exception is CancellationException) {
                        throw exception
                    }
                    val message = exception.message ?: exception.javaClass.simpleName
                    updateNotification(getString(R.string.notification_error, message))
                    runtimeStateRepository.registerFailed("❌ Error polling: $message")
                }

                delay(settings.pollingSeconds.coerceAtLeast(2L) * 1_000L)
            }
        }

        return START_STICKY
    }

    override fun onDestroy() {
        isRunning = false
        loopJob?.cancel()
        runtimeStateRepository.setPolling(false, "Polling dihentikan.")
        serviceScope.cancel()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun buildNotification(content: String): Notification {
        val activityIntent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this,
            1001,
            activityIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.app_name))
            .setContentText(content)
            .setSmallIcon(R.drawable.ic_bridge)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setContentIntent(pendingIntent)
            .build()
    }

    private fun updateNotification(content: String) {
        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.notify(NOTIFICATION_ID, buildNotification(content))
    }

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return
        }

        val channel = NotificationChannel(
            CHANNEL_ID,
            getString(R.string.channel_name),
            NotificationManager.IMPORTANCE_LOW,
        ).apply {
            description = getString(R.string.channel_description)
        }

        val manager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannel(channel)
    }

    companion object {
        private const val CHANNEL_ID = "print-bridge-service"
        private const val NOTIFICATION_ID = 2001

        @Volatile
        var isRunning = false
            private set
    }

    private fun resolvePayloadSource(job: id.webprint.bridge.data.PrintJob): String {
        return when {
            job.rawBytes != null -> "raw_base64"
            job.lines.isNotEmpty() -> "lines"
            job.receiptLayout != null -> "layout"
            job.kitchenTicket != null -> "ticket-layout"
            else -> "unknown"
        }
    }
}
