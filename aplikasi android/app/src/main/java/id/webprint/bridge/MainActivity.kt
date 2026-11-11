package id.webprint.bridge

import android.Manifest
import android.annotation.SuppressLint
import android.content.res.ColorStateList
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.LayoutInflater
import android.view.View
import android.view.WindowManager
import android.webkit.JsResult
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ArrayAdapter
import android.widget.RadioButton
import android.widget.RadioGroup
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.google.android.material.button.MaterialButton
import com.google.android.material.color.MaterialColors
import com.google.android.material.materialswitch.MaterialSwitch
import com.google.android.material.tabs.TabLayout
import com.google.android.material.textfield.TextInputEditText
import id.webprint.bridge.bridge.LakasirBridgeInterface
import id.webprint.bridge.bridge.ServiceController
import id.webprint.bridge.data.BluetoothTransportMode
import id.webprint.bridge.data.BridgeSettings
import id.webprint.bridge.data.PaperWidthPreset
import id.webprint.bridge.data.PrinterDevice
import id.webprint.bridge.data.PrinterMode
import id.webprint.bridge.data.QueueDiagnostics
import id.webprint.bridge.data.QueueType
import id.webprint.bridge.data.RuntimeStateRepository
import id.webprint.bridge.data.SettingsRepository
import id.webprint.bridge.databinding.ActivityMainBinding
import id.webprint.bridge.printer.BluetoothPrinterOption
import id.webprint.bridge.printer.EscPosPrinter
import id.webprint.bridge.printer.PrinterDiagnostics
import id.webprint.bridge.printer.BluetoothPrinterRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var settingsRepository: SettingsRepository
    private lateinit var runtimeStateRepository: RuntimeStateRepository
    private lateinit var serviceController: ServiceController
    private lateinit var bluetoothPrinterRepository: BluetoothPrinterRepository
    private lateinit var printerDiagnostics: PrinterDiagnostics
    private lateinit var escPosPrinter: EscPosPrinter
    private lateinit var lakasirBridgeInterface: LakasirBridgeInterface
    private val queueDiagnostics = QueueDiagnostics()
    private val currentPrinters = mutableListOf<PrinterDevice>()
    private val uiScope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private var refreshTick = 0
    private var lastAlertMessage: String? = null
    private val refreshHandler = Handler(Looper.getMainLooper())
    private val refreshRunnable = object : Runnable {
        override fun run() {
            renderStatus()
            refreshTick++
            if (refreshTick % 10 == 0) {
                runPassiveHealthCheck()
            }
            refreshHandler.postDelayed(this, 1000L)
        }
    }

    private val notificationPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { granted ->
        if (!granted) {
            Toast.makeText(this, R.string.notification_permission_warning, Toast.LENGTH_LONG).show()
        }
    }

    private val bluetoothPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions(),
    ) { result ->
        if (result.values.all { it }) {
            showBluetoothPrinterPicker()
        } else {
            Toast.makeText(this, R.string.bluetooth_permission_warning, Toast.LENGTH_LONG).show()
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Keep screen awake for cashier desk usage
        window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        settingsRepository = SettingsRepository(this)
        runtimeStateRepository = RuntimeStateRepository(this)
        serviceController = ServiceController(this)
        bluetoothPrinterRepository = BluetoothPrinterRepository()
        printerDiagnostics = PrinterDiagnostics(this)
        escPosPrinter = EscPosPrinter(this)

        requestNotificationPermissionIfNeeded()
        setupBackPressHandler()
        setupTabs()
        bindPaperWidthPresetOptions()
        bindForm()
        bindActions()
        setupWebView()

        val incomingUrl = intent?.dataString ?: intent?.getStringExtra("url")
        if (!incomingUrl.isNullOrBlank()) {
            binding.clientUrlInput.setText(incomingUrl)
            importSettingsFromClientUrl()
        }

        val settings = settingsRepository.load()
        if ((settings.backgroundServerEnabled || settings.autoStart) && !isServiceActive()) {
            serviceController.start()
        }

        // Default to Fullscreen POS mode
        showSection(pos = true, dashboard = false, settings = false, logs = false)

        renderStatus()
    }

    override fun onNewIntent(intent: android.content.Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        val incomingUrl = intent.dataString ?: intent.getStringExtra("url")
        if (!incomingUrl.isNullOrBlank()) {
            binding.clientUrlInput.setText(incomingUrl)
            importSettingsFromClientUrl()
        }
    }

    private fun setupBackPressHandler() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (binding.mainTabLayout.selectedTabPosition == 0) {
                    if (binding.posWebView.canGoBack()) {
                        binding.posWebView.goBack()
                        return
                    }
                } else {
                    binding.mainTabLayout.getTabAt(0)?.select()
                    return
                }

                AlertDialog.Builder(this@MainActivity)
                    .setTitle("Keluar Aplikasi?")
                    .setMessage("Apakah Anda yakin ingin keluar dari POS Lakasir?")
                    .setPositiveButton("Keluar") { _, _ -> finish() }
                    .setNegativeButton("Batal", null)
                    .show()
            }
        })
    }

    private fun setupTabs() {
        binding.mainTabLayout.addOnTabSelectedListener(object : TabLayout.OnTabSelectedListener {
            override fun onTabSelected(tab: TabLayout.Tab?) {
                when (tab?.position) {
                    0 -> showSection(pos = true, dashboard = false, settings = false, logs = false)
                    1 -> showSection(pos = false, dashboard = true, settings = false, logs = false)
                    2 -> showSection(pos = false, dashboard = false, settings = true, logs = false)
                    3 -> showSection(pos = false, dashboard = false, settings = false, logs = true)
                }
            }

            override fun onTabUnselected(tab: TabLayout.Tab?) {}
            override fun onTabReselected(tab: TabLayout.Tab?) {}
        })
    }

    private fun showSection(pos: Boolean, dashboard: Boolean, settings: Boolean, logs: Boolean) {
        binding.appBarLayout.visibility = if (pos) View.GONE else View.VISIBLE
        binding.appBarLayout.setExpanded(!pos, false)
        binding.sectionPos.visibility = if (pos) View.VISIBLE else View.GONE
        binding.contentScrollView.visibility = if (pos) View.GONE else View.VISIBLE
        binding.sectionDashboard.visibility = if (dashboard) View.VISIBLE else View.GONE
        binding.sectionSettings.visibility = if (settings) View.VISIBLE else View.GONE
        binding.sectionLogs.visibility = if (logs) View.VISIBLE else View.GONE
    }

    @SuppressLint("SetJavaScriptEnabled")
    private fun setupWebView() {
        binding.posWebView.isVerticalScrollBarEnabled = true
        binding.posWebView.isHorizontalScrollBarEnabled = false
        binding.posWebView.overScrollMode = View.OVER_SCROLL_IF_CONTENT_SCROLLS

        val webSettings = binding.posWebView.settings
        webSettings.javaScriptEnabled = true
        webSettings.domStorageEnabled = true
        webSettings.databaseEnabled = true
        webSettings.allowFileAccess = true
        webSettings.mediaPlaybackRequiresUserGesture = false
        webSettings.loadWithOverviewMode = false
        webSettings.useWideViewPort = false
        webSettings.textZoom = 100
        webSettings.cacheMode = WebSettings.LOAD_DEFAULT
        webSettings.mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW

        lakasirBridgeInterface = LakasirBridgeInterface(
            context = this,
            escPosPrinter = escPosPrinter,
            settingsRepository = settingsRepository,
            onOpenSettingsRequested = {
                binding.mainTabLayout.getTabAt(2)?.select()
            },
            onReloadRequested = {
                binding.posWebView.reload()
            },
        )
        binding.posWebView.addJavascriptInterface(lakasirBridgeInterface, "LakasirBridge")

        binding.posWebView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                if (newProgress < 100) {
                    binding.posProgressBar.visibility = View.VISIBLE
                    binding.posProgressBar.progress = newProgress
                } else {
                    binding.posProgressBar.visibility = View.GONE
                }
            }

            override fun onJsAlert(view: WebView?, url: String?, message: String?, result: JsResult?): Boolean {
                AlertDialog.Builder(this@MainActivity)
                    .setMessage(message)
                    .setPositiveButton(android.R.string.ok) { _, _ -> result?.confirm() }
                    .setCancelable(false)
                    .show()
                return true
            }

            override fun onJsConfirm(view: WebView?, url: String?, message: String?, result: JsResult?): Boolean {
                AlertDialog.Builder(this@MainActivity)
                    .setMessage(message)
                    .setPositiveButton(android.R.string.ok) { _, _ -> result?.confirm() }
                    .setNegativeButton(android.R.string.cancel) { _, _ -> result?.cancel() }
                    .setCancelable(false)
                    .show()
                return true
            }
        }

        binding.posWebView.webViewClient = object : WebViewClient() {
            @Suppress("DEPRECATION")
            override fun onReceivedError(view: WebView?, errorCode: Int, description: String?, failingUrl: String?) {
                super.onReceivedError(view, errorCode, description, failingUrl)
                Toast.makeText(this@MainActivity, "Gagal memuat POS: $description", Toast.LENGTH_SHORT).show()
            }
        }

        binding.fabPrinterSettings.setOnClickListener {
            binding.mainTabLayout.getTabAt(2)?.select()
        }

        binding.posSetupButton.setOnClickListener {
            binding.mainTabLayout.getTabAt(2)?.select()
        }

        loadPosUrl()
    }

    private fun loadPosUrl() {
        val settings = settingsRepository.load()
        val targetUrl = settings.resolvedPosUrl()
        if (targetUrl.isNotBlank()) {
            binding.posEmptyState.visibility = View.GONE
            binding.posWebView.visibility = View.VISIBLE
            binding.posWebView.loadUrl(targetUrl)
        } else {
            binding.posEmptyState.visibility = View.VISIBLE
            binding.posWebView.visibility = View.GONE
        }
    }

    override fun onResume() {
        super.onResume()
        renderStatus()
        refreshHandler.post(refreshRunnable)
    }

    override fun onPause() {
        refreshHandler.removeCallbacks(refreshRunnable)
        super.onPause()
    }

    override fun onDestroy() {
        uiScope.cancel()
        super.onDestroy()
    }

    private fun bindForm() {
        val settings = settingsRepository.load()
        binding.clientUrlInput.setText(settings.clientUrl)
        binding.baseUrlInput.setText(settings.baseUrl)
        binding.posUrlInput.setText(settings.posUrl)
        binding.apiKeyInput.setText(settings.token)
        binding.outletIdInput.setText(settings.outletId.toString())
        binding.deviceIdInput.setText(settings.deviceId)
        binding.stationIdInput.setText(settings.stationId)
        binding.paperWidthPresetInput.setText(
            paperWidthPresetLabel(PaperWidthPreset.fromValue(settings.paperWidthPreset)),
            false,
        )
        binding.paperWidthCustomColumnsInput.setText(settings.paperWidthColumns.toString())
        binding.pollingSecondsInput.setText(settings.pollingSeconds.toString())
        binding.autoStartSwitch.isChecked = settings.autoStart
        binding.backgroundServerSwitch.isChecked = settings.backgroundServerEnabled

        binding.queueTypeGroup.check(
            if (QueueType.fromValue(settings.queueType) == QueueType.KITCHEN) {
                R.id.queueTypeKitchen
            } else {
                R.id.queueTypeCashier
            },
        )

        binding.printerModeGroup.check(
            if (PrinterMode.fromValue(settings.printerMode) == PrinterMode.BLUETOOTH) {
                R.id.printerModeBluetooth
            } else {
                R.id.printerModeTcp
            },
        )

        binding.printerHostInput.setText(settings.printerHost)
        binding.printerPortInput.setText(settings.printerPort.toString())
        binding.bluetoothMacAddressInput.setText(settings.bluetoothMacAddress)
        binding.bluetoothPrinterNameInput.setText(settings.bluetoothPrinterName)
        binding.bluetoothTransportGroup.check(
            when (BluetoothTransportMode.fromValue(settings.bluetoothTransportMode)) {
                BluetoothTransportMode.CLASSIC -> R.id.bluetoothTransportClassic
                BluetoothTransportMode.BLE -> R.id.bluetoothTransportBle
                BluetoothTransportMode.AUTO -> R.id.bluetoothTransportAuto
            },
        )

        updateQueueTypeSection()
        updatePrinterModeSections()
        updatePaperWidthSection()

        currentPrinters.clear()
        currentPrinters.addAll(settings.printers)
        renderPrinterDevicesList()
    }

    private fun bindActions() {
        binding.btnBackToPos.setOnClickListener {
            binding.mainTabLayout.getTabAt(0)?.select()
        }

        binding.btnAddPrinter.setOnClickListener {
            showPrinterDeviceDialog(null)
        }

        binding.importClientUrlButton.setOnClickListener {
            importSettingsFromClientUrl()
        }

        binding.queueTypeGroup.setOnCheckedChangeListener { _, _ ->
            updateQueueTypeSection()
        }

        binding.printerModeGroup.setOnCheckedChangeListener { _, _ ->
            updatePrinterModeSections()
        }

        binding.paperWidthPresetInput.setOnItemClickListener { _, _, _, _ ->
            updatePaperWidthSection()
        }

        binding.chooseBluetoothPrinterButton.setOnClickListener {
            ensureBluetoothPermissionsAndPick()
        }

        binding.testConnectionButton.setOnClickListener {
            runConnectionCheck()
        }

        binding.testPrinterButton.setOnClickListener {
            runPrinterTest()
        }

        binding.openDrawerButton.setOnClickListener {
            runOpenCashDrawer()
        }

        binding.clearLogsButton.setOnClickListener {
            runtimeStateRepository.clearLogs()
            renderStatus()
            showSuccessToast("Log aktivitas dibersihkan.")
        }

        binding.saveButton.setOnClickListener {
            val updated = collectSettingsFromForm() ?: return@setOnClickListener
            settingsRepository.save(updated)
            if (updated.backgroundServerEnabled && !isServiceActive()) {
                serviceController.start()
            } else if (!updated.backgroundServerEnabled && isServiceActive()) {
                serviceController.stop()
            }
            loadPosUrl()
            renderStatus(getString(R.string.settings_saved))
            showSuccessToast(getString(R.string.settings_saved))
        }

        binding.startButton.setOnClickListener {
            if (isServiceActive()) {
                serviceController.stop()
                runtimeStateRepository.updateStatus("Permintaan stop service dikirim.")
                renderStatus(getString(R.string.service_stop_requested))
                return@setOnClickListener
            }

            val updated = collectSettingsFromForm() ?: return@setOnClickListener
            settingsRepository.save(updated)
            runtimeStateRepository.reset()
            uiScope.launch {
                setBusy(true)
                val queueResult = withContext(Dispatchers.IO) { queueDiagnostics.check(updated) }
                val printerResult = withContext(Dispatchers.IO) { printerDiagnostics.validate(updated) }
                runtimeStateRepository.updateStatus(
                    if (queueResult.ok && printerResult.ok) {
                        getString(R.string.connection_check_ok)
                    } else {
                        getString(R.string.connection_check_failed)
                    },
                )
                if (!queueResult.ok || !printerResult.ok) {
                    renderStatus(getString(R.string.start_blocked_validation))
                    showErrorAlert(
                        title = getString(R.string.alert_validation_failed_title),
                        message = "${queueResult.message}\n${printerResult.message}",
                    )
                    setBusy(false)
                    return@launch
                }
                serviceController.start()
                renderStatus(getString(R.string.service_start_requested))
                showSuccessToast(getString(R.string.service_start_requested))
                setBusy(false)
            }
        }
    }

    private fun renderStatus(message: String? = null) {
        val settings = settingsRepository.load()
        val runtime = runtimeStateRepository.load()
        val serviceActive = isServiceActive(runtime)

        // Status Header Chip & Hero Card
        if (serviceActive) {
            binding.bridgeStatusChip.text = getString(R.string.service_active_badge)
            binding.bridgeStatusChip.setBackgroundResource(R.drawable.bg_status_badge_active)
            binding.bridgeStatusChip.setTextColor(ContextCompat.getColor(this, R.color.mataverse_success))

            binding.dashboardStatusIcon.setBackgroundResource(R.drawable.bg_status_badge_active)
            binding.dashboardStatusIcon.imageTintList = ColorStateList.valueOf(
                ContextCompat.getColor(this, R.color.mataverse_success),
            )
            binding.dashboardStatusTitle.text = getString(R.string.service_running)
            binding.dashboardStatusSubtitle.text = "Menerima antrean: Outlet #${settings.outletId} (${QueueType.fromValue(settings.queueType).name})"
        } else {
            binding.bridgeStatusChip.text = getString(R.string.service_stopped_badge)
            binding.bridgeStatusChip.setBackgroundResource(R.drawable.bg_status_badge_inactive)
            binding.bridgeStatusChip.setTextColor(ContextCompat.getColor(this, R.color.mataverse_error))

            binding.dashboardStatusIcon.setBackgroundResource(R.drawable.bg_status_badge_inactive)
            binding.dashboardStatusIcon.imageTintList = ColorStateList.valueOf(
                ContextCompat.getColor(this, R.color.mataverse_error),
            )
            binding.dashboardStatusTitle.text = getString(R.string.service_stopped)
            binding.dashboardStatusSubtitle.text = "Bridge nonaktif. Klik tombol di bawah untuk menyalakan."
        }

        // Dashboard Server Card
        binding.dashboardServerText.text = if (settings.baseUrl.isNotBlank()) {
            "Outlet #${settings.outletId} • ${QueueType.fromValue(settings.queueType).name}\n${settings.baseUrl}"
        } else {
            "Server belum dikonfigurasi"
        }

        // Dashboard Printer Card
        binding.dashboardPrinterText.text = if (settings.printers.isNotEmpty()) {
            val def = settings.defaultPrinter()
            "${settings.printers.size} Printer Terdaftar\nDefault: ${def?.name ?: "-"}"
        } else {
            when (PrinterMode.fromValue(settings.printerMode)) {
                PrinterMode.TCP -> "LAN ${settings.printerHost.ifBlank { "IP belum diatur" }}:${settings.printerPort}\nPreset: ${settings.paperWidthPreset}"
                PrinterMode.BLUETOOTH -> "${settings.bluetoothPrinterName.ifBlank { "Printer Bluetooth" }}\n[${settings.bluetoothMacAddress.ifBlank { "Belum dipilih" }}]"
            }
        }

        // Dashboard Log Preview (top 3)
        binding.dashboardLogPreview.text = runtime.logs.take(3).joinToString("\n")

        val status = buildString {
            appendLine(
                if (serviceActive) {
                    getString(R.string.service_running)
                } else {
                    getString(R.string.service_stopped)
                },
            )
            appendLine(getString(R.string.status_queue_type, QueueType.fromValue(settings.queueType).name))
            appendLine(getString(R.string.status_outlet_id, settings.outletId))
            appendLine(getString(R.string.status_token, settings.token.ifBlank { "-" }))
            appendLine(getString(R.string.status_device_id, settings.deviceId.ifBlank { "-" }))
            appendLine(getString(R.string.status_server, settings.baseUrl.ifBlank { "-" }))
            appendLine(getString(R.string.status_printer_mode, PrinterMode.fromValue(settings.printerMode).name))
            appendLine(printerSummary(settings))
            appendLine(getString(R.string.status_polling, settings.pollingSeconds))
            if (!message.isNullOrBlank()) {
                appendLine()
                appendLine(message)
            }
        }
        binding.statusText.text = status.trim()
        binding.statusBanner.text = runtime.lastStatus
        binding.statPrinted.text = runtime.printedCount.toString()
        binding.statFailed.text = runtime.failedCount.toString()
        binding.statPolls.text = runtime.pollsCount.toString()
        binding.logText.text = runtime.logs.joinToString("\n")
        updateServiceButton(serviceActive)
        if (runtime.lastStatus.contains("❌") || runtime.lastStatus.contains("gagal", ignoreCase = true)) {
            showRuntimeAlertOnce(runtime.lastStatus)
        }
    }

    private fun printerSummary(settings: BridgeSettings): String {
        return when (PrinterMode.fromValue(settings.printerMode)) {
            PrinterMode.TCP -> getString(
                R.string.status_printer_tcp,
                settings.printerHost.ifBlank { "-" },
                settings.printerPort,
            )
            PrinterMode.BLUETOOTH -> getString(
                R.string.status_printer_bluetooth,
                settings.bluetoothPrinterName.ifBlank { "-" },
                settings.bluetoothMacAddress.ifBlank { "-" },
            ) + " / " + BluetoothTransportMode.fromValue(settings.bluetoothTransportMode).name
        }
    }

    private fun requestNotificationPermissionIfNeeded() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return
        }

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) ==
            PackageManager.PERMISSION_GRANTED
        ) {
            return
        }

        notificationPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
    }

    private fun currentPrinterMode(): PrinterMode {
        return if (binding.printerModeBluetooth.isChecked) {
            PrinterMode.BLUETOOTH
        } else {
            PrinterMode.TCP
        }
    }

    private fun currentQueueType(): QueueType {
        return if (binding.queueTypeKitchen.isChecked) {
            QueueType.KITCHEN
        } else {
            QueueType.CASHIER
        }
    }

    private fun updatePrinterModeSections() {
        val mode = currentPrinterMode()
        binding.tcpPrinterSection.visibility = if (mode == PrinterMode.TCP) View.VISIBLE else View.GONE
        binding.bluetoothPrinterSection.visibility = if (mode == PrinterMode.BLUETOOTH) View.VISIBLE else View.GONE
    }

    private fun updateQueueTypeSection() {
        binding.stationIdSection.visibility = if (currentQueueType() == QueueType.KITCHEN) View.VISIBLE else View.GONE
    }

    private fun ensureBluetoothPermissionsAndPick() {
        if (!bluetoothPrinterRepository.isBluetoothAvailable()) {
            Toast.makeText(this, R.string.bluetooth_not_available, Toast.LENGTH_LONG).show()
            return
        }

        if (!hasBluetoothPermissions()) {
            requestBluetoothPermissions()
            return
        }

        showBluetoothPrinterPicker()
    }

    private fun hasBluetoothPermissions(): Boolean {
        return requiredBluetoothPermissions().all { permission ->
            ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED
        }
    }

    private fun requestBluetoothPermissions() {
        val permissions = requiredBluetoothPermissions()
        if (permissions.isEmpty()) {
            showBluetoothPrinterPicker()
            return
        }
        bluetoothPermissionLauncher.launch(permissions)
    }

    private fun requiredBluetoothPermissions(): Array<String> {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            arrayOf(
                Manifest.permission.BLUETOOTH_CONNECT,
                Manifest.permission.BLUETOOTH_SCAN,
            )
        } else {
            emptyArray()
        }
    }

    private fun showBluetoothPrinterPicker() {
        if (!bluetoothPrinterRepository.isBluetoothEnabled()) {
            Toast.makeText(this, R.string.bluetooth_enable_first, Toast.LENGTH_LONG).show()
            return
        }

        val printers = bluetoothPrinterRepository.getBondedPrinters()
        if (printers.isEmpty()) {
            Toast.makeText(this, R.string.bluetooth_no_paired_printer, Toast.LENGTH_LONG).show()
            return
        }

        val items = printers.map { it.label }.toTypedArray()
        val currentAddress = binding.bluetoothMacAddressInput.text?.toString()?.trim()
        val selectedIndex = printers.indexOfFirst { it.address.equals(currentAddress, ignoreCase = true) }

        AlertDialog.Builder(this)
            .setTitle(R.string.dialog_bluetooth_printer_title)
            .setSingleChoiceItems(items, selectedIndex) { dialog, which ->
                val printer: BluetoothPrinterOption = printers[which]
                binding.bluetoothMacAddressInput.setText(printer.address)
                binding.bluetoothPrinterNameInput.setText(printer.name)
                dialog.dismiss()
            }
            .setNegativeButton(android.R.string.cancel, null)
            .show()
    }

    private fun currentBluetoothTransportMode(): BluetoothTransportMode {
        return when (binding.bluetoothTransportGroup.checkedRadioButtonId) {
            R.id.bluetoothTransportClassic -> BluetoothTransportMode.CLASSIC
            R.id.bluetoothTransportBle -> BluetoothTransportMode.BLE
            else -> BluetoothTransportMode.AUTO
        }
    }

    private fun importSettingsFromClientUrl() {
        val rawUrl = binding.clientUrlInput.text.toString().trim()
        if (rawUrl.isBlank()) {
            binding.statusText.text = getString(R.string.error_client_url_required)
            return
        }

        val uri = runCatching { Uri.parse(rawUrl) }.getOrNull()
        if (uri == null) {
            binding.statusText.text = getString(R.string.error_client_url_invalid)
            return
        }

        val imported = settingsRepository.load().copy(
            clientUrl = rawUrl,
            baseUrl = uri.getQueryParameter("base_url")?.trim()?.removeSuffix("/").orEmpty(),
            token = uri.getQueryParameter("token")?.trim().orEmpty(),
            outletId = uri.getQueryParameter("outlet_id")?.trim()?.toIntOrNull() ?: 0,
            queueType = QueueType.fromValue(uri.getQueryParameter("type")).value,
            stationId = uri.getQueryParameter("station_id")?.trim().orEmpty(),
            paperWidthPreset = parsePaperWidthPresetFromClientUrl(uri.getQueryParameter("paper")).value,
            autoStart = uri.getQueryParameter("autostart") == "1" ||
                uri.getQueryParameter("autostart").equals("true", ignoreCase = true),
        )

        settingsRepository.save(imported)
        if (imported.autoStart) {
            runtimeStateRepository.reset()
            serviceController.start()
        }
        bindForm()
        loadPosUrl()
        renderStatus(getString(R.string.client_url_imported))
    }

    private fun collectSettingsFromForm(validatePrinter: Boolean = true): BridgeSettings? {
        val current = settingsRepository.load()
        val paperWidthPreset = currentPaperWidthPreset()
        val updated = current.copy(
            clientUrl = binding.clientUrlInput.text.toString().trim(),
            baseUrl = binding.baseUrlInput.text.toString().trim().removeSuffix("/"),
            posUrl = binding.posUrlInput.text.toString().trim(),
            token = binding.apiKeyInput.text.toString().trim(),
            outletId = binding.outletIdInput.text.toString().trim().toIntOrNull() ?: 0,
            queueType = currentQueueType().value,
            deviceId = binding.deviceIdInput.text.toString().trim(),
            stationId = binding.stationIdInput.text.toString().trim(),
            paperWidthPreset = paperWidthPreset.value,
            paperWidthColumns = when (paperWidthPreset) {
                PaperWidthPreset.AUTO -> current.paperWidthColumns.takeIf { it > 0 } ?: 32
                PaperWidthPreset.WIDTH_58 -> 32
                PaperWidthPreset.WIDTH_80 -> 48
                PaperWidthPreset.CUSTOM -> binding.paperWidthCustomColumnsInput.text.toString().trim().toIntOrNull() ?: 32
            },
            printerMode = currentPrinterMode().value,
            printerHost = binding.printerHostInput.text.toString().trim(),
            printerPort = binding.printerPortInput.text.toString().trim().toIntOrNull() ?: 9100,
            bluetoothMacAddress = binding.bluetoothMacAddressInput.text.toString().trim(),
            bluetoothPrinterName = binding.bluetoothPrinterNameInput.text.toString().trim(),
            bluetoothTransportMode = currentBluetoothTransportMode().value,
            pollingSeconds = binding.pollingSecondsInput.text.toString().trim().toLongOrNull() ?: 3L,
            autoStart = binding.autoStartSwitch.isChecked,
            backgroundServerEnabled = binding.backgroundServerSwitch.isChecked,
            printers = currentPrinters,
        )

        val errors = settingsRepository.validateServer(updated).toMutableList()
        if (updated.baseUrl.contains("localhost")) {
            errors += getString(R.string.error_localhost_android)
        }
        if (validatePrinter) {
            errors += settingsRepository.validatePrinter(updated)
        }
        if (errors.isNotEmpty()) {
            val text = errors.joinToString("\n")
            binding.statusText.text = text
            runtimeStateRepository.updateStatus("Validasi gagal.")
            showErrorAlert(
                title = getString(R.string.alert_validation_failed_title),
                message = text,
            )
            return null
        }

        return updated
    }

    private fun bindPaperWidthPresetOptions() {
        val entries = listOf(
            paperWidthPresetLabel(PaperWidthPreset.AUTO),
            paperWidthPresetLabel(PaperWidthPreset.WIDTH_58),
            paperWidthPresetLabel(PaperWidthPreset.WIDTH_80),
            paperWidthPresetLabel(PaperWidthPreset.CUSTOM),
        )

        binding.paperWidthPresetInput.setAdapter(
            ArrayAdapter(this, android.R.layout.simple_list_item_1, entries),
        )
    }

    private fun currentPaperWidthPreset(): PaperWidthPreset {
        val selected = binding.paperWidthPresetInput.text?.toString()?.trim().orEmpty()

        return when (selected) {
            paperWidthPresetLabel(PaperWidthPreset.AUTO) -> PaperWidthPreset.AUTO
            paperWidthPresetLabel(PaperWidthPreset.WIDTH_80) -> PaperWidthPreset.WIDTH_80
            paperWidthPresetLabel(PaperWidthPreset.CUSTOM) -> PaperWidthPreset.CUSTOM
            else -> PaperWidthPreset.WIDTH_58
        }
    }

    private fun updatePaperWidthSection() {
        val customSelected = currentPaperWidthPreset() == PaperWidthPreset.CUSTOM
        binding.paperWidthCustomColumnsLayout.visibility = if (customSelected) View.VISIBLE else View.GONE
    }

    private fun paperWidthPresetLabel(preset: PaperWidthPreset): String {
        return when (preset) {
            PaperWidthPreset.AUTO -> getString(R.string.paper_width_preset_auto)
            PaperWidthPreset.WIDTH_58 -> getString(R.string.paper_width_preset_58mm)
            PaperWidthPreset.WIDTH_80 -> getString(R.string.paper_width_preset_80mm)
            PaperWidthPreset.CUSTOM -> getString(R.string.paper_width_preset_custom)
        }
    }

    private fun parsePaperWidthPresetFromClientUrl(value: String?): PaperWidthPreset {
        return when (value?.trim()?.lowercase()) {
            "80mm", "48" -> PaperWidthPreset.WIDTH_80
            "custom" -> PaperWidthPreset.CUSTOM
            "auto" -> PaperWidthPreset.AUTO
            else -> PaperWidthPreset.WIDTH_58
        }
    }

    private fun runConnectionCheck() {
        val settings = collectSettingsFromForm(validatePrinter = false) ?: return
        settingsRepository.save(settings)
        uiScope.launch {
            setBusy(true)
            val queueResult = withContext(Dispatchers.IO) { queueDiagnostics.check(settings) }
            val printerResult = withContext(Dispatchers.IO) { printerDiagnostics.validate(settings) }

            if (queueResult.ok) {
                val fullOk = printerResult.ok
                val statusMsg = if (fullOk) {
                    getString(R.string.connection_check_ok)
                } else {
                    "Server OK. Printer: ${printerResult.message}"
                }
                runtimeStateRepository.updateStatus(statusMsg)
                showSuccessToast("✅ Server POS Terhubung (${queueResult.message})")
                renderStatus(statusMsg)
            } else {
                runtimeStateRepository.updateStatus(getString(R.string.connection_check_failed))
                showErrorAlert(
                    title = getString(R.string.alert_connection_failed_title),
                    message = "${queueResult.message}\n${printerResult.message}",
                )
                renderStatus(getString(R.string.connection_check_failed))
            }
            setBusy(false)
        }
    }

    private fun runPrinterTest() {
        if (currentPrinters.size > 1) {
            val options = mutableListOf("⚡ Uji Semua Printer Sekaligus")
            options.addAll(currentPrinters.map { "[${it.id}] ${it.name} (${if (it.isDefault) "Default" else it.mode})" })

            AlertDialog.Builder(this)
                .setTitle("Pilih Printer yang Ingin Diuji")
                .setItems(options.toTypedArray()) { _, which ->
                    if (which == 0) {
                        currentPrinters.forEach { runDeviceTestPrint(it) }
                    } else {
                        val selected = currentPrinters[which - 1]
                        runDeviceTestPrint(selected)
                    }
                }
                .setNegativeButton("Batal", null)
                .show()
            return
        }

        if (currentPrinters.isNotEmpty()) {
            runDeviceTestPrint(currentPrinters.first())
            return
        }

        val settings = collectSettingsFromForm() ?: return
        settingsRepository.save(settings)
        uiScope.launch {
            setBusy(true)
            val printerCheck = withContext(Dispatchers.IO) { printerDiagnostics.validate(settings) }
            if (!printerCheck.ok) {
                runtimeStateRepository.updateStatus(printerCheck.message)
                renderStatus(getString(R.string.printer_test_failed))
                setBusy(false)
                return@launch
            }
            val printResult = withContext(Dispatchers.IO) { printerDiagnostics.testPrint(settings) }
            if (printResult.ok) {
                runtimeStateRepository.registerPrinted("✅ Test print berhasil")
                renderStatus(getString(R.string.printer_test_ok))
                showSuccessToast(getString(R.string.printer_test_ok))
            } else {
                runtimeStateRepository.registerFailed("❌ ${printResult.message}")
                renderStatus(getString(R.string.printer_test_failed))
                showErrorAlert(
                    title = getString(R.string.alert_printer_failed_title),
                    message = printResult.message,
                )
            }
            setBusy(false)
        }
    }

    private fun renderPrinterDevicesList() {
        binding.printerListContainer.removeAllViews()
        if (currentPrinters.isEmpty()) {
            binding.emptyPrintersNotice.visibility = View.VISIBLE
            return
        }
        binding.emptyPrintersNotice.visibility = View.GONE

        val inflater = LayoutInflater.from(this)
        for (device in currentPrinters) {
            val itemView = inflater.inflate(R.layout.item_printer_device, binding.printerListContainer, false)
            val tvName = itemView.findViewById<TextView>(R.id.tvDeviceName)
            val tvAlias = itemView.findViewById<TextView>(R.id.tvDeviceAlias)
            val tvSummary = itemView.findViewById<TextView>(R.id.tvDeviceSummary)
            val chipDefault = itemView.findViewById<TextView>(R.id.chipDefault)
            val btnTest = itemView.findViewById<MaterialButton>(R.id.btnTestDevice)
            val btnEdit = itemView.findViewById<MaterialButton>(R.id.btnEditDevice)
            val btnDelete = itemView.findViewById<MaterialButton>(R.id.btnDeleteDevice)

            tvName.text = device.name
            tvAlias.text = device.id
            tvSummary.text = device.summary()
            chipDefault.visibility = if (device.isDefault) View.VISIBLE else View.GONE

            btnTest.setOnClickListener {
                runDeviceTestPrint(device)
            }

            btnEdit.setOnClickListener {
                showPrinterDeviceDialog(device)
            }

            btnDelete.setOnClickListener {
                confirmDeletePrinterDevice(device)
            }

            binding.printerListContainer.addView(itemView)
        }
    }

    private fun runDeviceTestPrint(device: PrinterDevice) {
        uiScope.launch {
            setBusy(true)
            runtimeStateRepository.updateStatus("Uji cetak ke '${device.name}' (${device.id})...")
            renderStatus()
            val result = withContext(Dispatchers.IO) {
                printerDiagnostics.testPrintDevice(device)
            }
            if (result.ok) {
                runtimeStateRepository.registerPrinted(result.message)
                showSuccessToast(result.message)
            } else {
                runtimeStateRepository.registerFailed(result.message)
                showErrorAlert("Uji Cetak Gagal", result.message)
            }
            renderStatus()
            setBusy(false)
        }
    }

    private fun showPrinterDeviceDialog(deviceToEdit: PrinterDevice?) {
        val dialogView = LayoutInflater.from(this).inflate(R.layout.dialog_printer_device, null)
        val inputAlias = dialogView.findViewById<TextInputEditText>(R.id.inputDeviceAlias)
        val inputName = dialogView.findViewById<TextInputEditText>(R.id.inputDeviceName)
        val modeGroup = dialogView.findViewById<RadioGroup>(R.id.dialogModeGroup)
        val rbBt = dialogView.findViewById<RadioButton>(R.id.dialogModeBluetooth)
        val rbTcp = dialogView.findViewById<RadioButton>(R.id.dialogModeTcp)
        val btSection = dialogView.findViewById<View>(R.id.dialogBtSection)
        val tcpSection = dialogView.findViewById<View>(R.id.dialogTcpSection)
        val btnSelectBt = dialogView.findViewById<MaterialButton>(R.id.dialogBtnSelectBt)
        val inputBtMac = dialogView.findViewById<TextInputEditText>(R.id.inputDeviceBtMac)
        val inputTcpHost = dialogView.findViewById<TextInputEditText>(R.id.inputDeviceTcpHost)
        val inputTcpPort = dialogView.findViewById<TextInputEditText>(R.id.inputDeviceTcpPort)
        val paperGroup = dialogView.findViewById<RadioGroup>(R.id.dialogPaperGroup)
        val rbPaper58 = dialogView.findViewById<RadioButton>(R.id.dialogPaper58)
        val rbPaper80 = dialogView.findViewById<RadioButton>(R.id.dialogPaper80)
        val switchDefault = dialogView.findViewById<MaterialSwitch>(R.id.switchIsDefault)
        val tvTitle = dialogView.findViewById<TextView>(R.id.dialogTitle)

        var selectedBtName = deviceToEdit?.bluetoothPrinterName.orEmpty()

        modeGroup.setOnCheckedChangeListener { _, checkedId ->
            val isBt = checkedId == R.id.dialogModeBluetooth
            btSection.visibility = if (isBt) View.VISIBLE else View.GONE
            tcpSection.visibility = if (isBt) View.GONE else View.VISIBLE
        }

        btnSelectBt.setOnClickListener {
            val printers = bluetoothPrinterRepository.getBondedPrinters()
            if (printers.isEmpty()) {
                Toast.makeText(this, R.string.bluetooth_no_paired_printer, Toast.LENGTH_SHORT).show()
                return@setOnClickListener
            }
            val labels = printers.map { it.label }.toTypedArray()
            AlertDialog.Builder(this)
                .setTitle("Pilih Printer Bluetooth")
                .setItems(labels) { _, which ->
                    val chosen = printers[which]
                    inputBtMac.setText(chosen.address)
                    selectedBtName = chosen.name
                    if (inputName.text.isNullOrBlank()) {
                        inputName.setText(chosen.name)
                    }
                }
                .show()
        }

        if (deviceToEdit != null) {
            tvTitle.text = "Edit Perangkat Printer"
            inputAlias.setText(deviceToEdit.id)
            inputName.setText(deviceToEdit.name)
            if (deviceToEdit.mode == PrinterMode.TCP.value) {
                rbTcp.isChecked = true
                btSection.visibility = View.GONE
                tcpSection.visibility = View.VISIBLE
            } else {
                rbBt.isChecked = true
                btSection.visibility = View.VISIBLE
                tcpSection.visibility = View.GONE
            }
            inputBtMac.setText(deviceToEdit.bluetoothMacAddress)
            inputTcpHost.setText(deviceToEdit.host)
            inputTcpPort.setText(deviceToEdit.port.toString())
            if (deviceToEdit.paperWidthPreset == PaperWidthPreset.WIDTH_80.value) {
                rbPaper80.isChecked = true
            } else {
                rbPaper58.isChecked = true
            }
            switchDefault.isChecked = deviceToEdit.isDefault
        } else {
            tvTitle.text = "Tambah Perangkat Printer"
            if (currentPrinters.isEmpty()) {
                switchDefault.isChecked = true
                inputAlias.setText("kasir")
                inputName.setText("Printer Kasir")
            }
        }

        AlertDialog.Builder(this)
            .setView(dialogView)
            .setPositiveButton("Simpan", null)
            .setNegativeButton("Batal", null)
            .create().apply {
                setOnShowListener {
                    getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener {
                        val alias = inputAlias.text?.toString()?.trim().orEmpty().lowercase()
                        val name = inputName.text?.toString()?.trim().orEmpty()
                        if (alias.isBlank()) {
                            inputAlias.error = "Alias ID wajib diisi (contoh: kasir, dapur)"
                            return@setOnClickListener
                        }
                        if (name.isBlank()) {
                            inputName.error = "Nama wajib diisi"
                            return@setOnClickListener
                        }

                        val mode = if (rbBt.isChecked) PrinterMode.BLUETOOTH.value else PrinterMode.TCP.value
                        val mac = inputBtMac.text?.toString()?.trim().orEmpty()
                        val host = inputTcpHost.text?.toString()?.trim().orEmpty()
                        val port = inputTcpPort.text?.toString()?.trim()?.toIntOrNull() ?: 9100

                        if (mode == PrinterMode.BLUETOOTH.value && mac.isBlank()) {
                            inputBtMac.error = "Pilih printer Bluetooth atau masukkan MAC"
                            return@setOnClickListener
                        }
                        if (mode == PrinterMode.TCP.value && host.isBlank()) {
                            inputTcpHost.error = "IP printer wajib diisi"
                            return@setOnClickListener
                        }

                        val isDefault = switchDefault.isChecked
                        val paperPreset = if (rbPaper80.isChecked) PaperWidthPreset.WIDTH_80.value else PaperWidthPreset.WIDTH_58.value

                        if (isDefault) {
                            for (i in currentPrinters.indices) {
                                currentPrinters[i] = currentPrinters[i].copy(isDefault = false)
                            }
                        }

                        val newDevice = PrinterDevice(
                            id = alias,
                            name = name,
                            mode = mode,
                            host = host,
                            port = port,
                            bluetoothMacAddress = mac,
                            bluetoothPrinterName = selectedBtName,
                            bluetoothTransportMode = BluetoothTransportMode.AUTO.value,
                            paperWidthPreset = paperPreset,
                            paperWidthColumns = if (paperPreset == PaperWidthPreset.WIDTH_80.value) 48 else 32,
                            isDefault = isDefault || currentPrinters.isEmpty(),
                        )

                        val existingIndex = currentPrinters.indexOfFirst { it.id.equals(deviceToEdit?.id ?: alias, ignoreCase = true) }
                        if (existingIndex >= 0) {
                            currentPrinters[existingIndex] = newDevice
                        } else {
                            currentPrinters.add(newDevice)
                        }

                        savePrintersToRepository()
                        renderPrinterDevicesList()
                        showSuccessToast("Printer '$name' berhasil disimpan.")
                        dismiss()
                    }
                }
                show()
            }
    }

    private fun savePrintersToRepository() {
        val current = settingsRepository.load()
        val updated = current.copy(printers = currentPrinters)
        settingsRepository.save(updated)
    }

    private fun confirmDeletePrinterDevice(device: PrinterDevice) {
        AlertDialog.Builder(this)
            .setTitle("Hapus Printer?")
            .setMessage("Apakah Anda yakin ingin menghapus printer '${device.name}' (${device.id})?")
            .setPositiveButton("Hapus") { _, _ ->
                currentPrinters.removeAll { it.id == device.id }
                if (currentPrinters.isNotEmpty() && currentPrinters.none { it.isDefault }) {
                    currentPrinters[0] = currentPrinters[0].copy(isDefault = true)
                }
                savePrintersToRepository()
                renderPrinterDevicesList()
                showSuccessToast("Printer '${device.name}' dihapus.")
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    private fun runOpenCashDrawer() {
        val settings = collectSettingsFromForm() ?: settingsRepository.load()
        uiScope.launch {
            setBusy(true)
            val result = withContext(Dispatchers.IO) { printerDiagnostics.openCashDrawer(settings) }
            if (result.ok) {
                runtimeStateRepository.appendLog("💵 Buka laci kasir (drawer kick)")
                showSuccessToast(result.message)
            } else {
                runtimeStateRepository.registerFailed("❌ ${result.message}")
                showErrorAlert(
                    title = getString(R.string.alert_printer_failed_title),
                    message = result.message,
                )
            }
            renderStatus()
            setBusy(false)
        }
    }

    private fun setBusy(isBusy: Boolean) {
        binding.testConnectionButton.isEnabled = !isBusy
        binding.testPrinterButton.isEnabled = !isBusy
        binding.openDrawerButton.isEnabled = !isBusy
        binding.startButton.isEnabled = !isBusy
        binding.saveButton.isEnabled = !isBusy
        binding.importClientUrlButton.isEnabled = !isBusy
    }

    private fun isServiceActive(runtime: id.webprint.bridge.data.RuntimeState = runtimeStateRepository.load()): Boolean {
        return runtime.isPolling || serviceController.isRunning()
    }

    private fun updateServiceButton(isActive: Boolean) {
        binding.startButton.text = getString(
            if (isActive) R.string.button_stop_polling else R.string.button_start_polling,
        )
        val color = ContextCompat.getColor(
            this,
            if (isActive) R.color.mataverse_error else R.color.mataverse_primary,
        )
        binding.startButton.backgroundTintList = ColorStateList.valueOf(color)
        binding.stopButton.visibility = View.GONE
    }

    private fun runPassiveHealthCheck() {
        val settings = settingsRepository.load()
        if (!isServiceActive()) {
            return
        }
        uiScope.launch {
            val quickPrinter = withContext(Dispatchers.IO) { printerDiagnostics.quickCheck(settings) }
            if (!quickPrinter.ok) {
                runtimeStateRepository.updateStatus(quickPrinter.message)
                showRuntimeAlertOnce(quickPrinter.message)
                renderStatus()
            }
        }
    }

    private fun showSuccessToast(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
    }

    private fun showErrorAlert(title: String, message: String) {
        lastAlertMessage = message
        AlertDialog.Builder(this)
            .setTitle(title)
            .setMessage(message)
            .setPositiveButton(android.R.string.ok, null)
            .show()
    }

    private fun showRuntimeAlertOnce(message: String) {
        if (lastAlertMessage == message) {
            return
        }
        showErrorAlert(
            title = getString(R.string.alert_runtime_error_title),
            message = message,
        )
    }
}
