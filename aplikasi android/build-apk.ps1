# ==============================================================================
# Mataverse Hub - Automated Production APK Builder (Windows PowerShell)
# Usage:
#   .\build-apk.ps1 <version_name> [version_code] [-Install]
# Examples:
#   .\build-apk.ps1 1.0.1
#   .\build-apk.ps1 1.0.1 2
#   .\build-apk.ps1 1.0.1 2 -Install
#   .\build-apk.ps1 1.0.1 --install
# ==============================================================================

[CmdletBinding()]
param(
    [Parameter(Position = 0, Mandatory = $false)]
    [string]$VersionName,

    [Parameter(Position = 1, Mandatory = $false)]
    [string]$VersionCodeOrFlag,

    [Parameter(Position = 2, Mandatory = $false)]
    [string]$ExtraFlag,

    [switch]$Install
)

$ErrorActionPreference = "Stop"

# Tentukan folder root & android
$scriptDir = $PSScriptRoot
if (-not $scriptDir) {
    $scriptDir = (Get-Item .).FullName
}

$androidDir = Join-Path $scriptDir "aplikasi android"
if (-not (Test-Path $androidDir)) {
    $androidDir = Join-Path $scriptDir "android-app"
    if (-not (Test-Path $androidDir)) {
        # Jika dijalankan langsung dari dalam folder android
        if (Test-Path (Join-Path $scriptDir "app\build.gradle.kts")) {
            $androidDir = $scriptDir
        } else {
            Write-Host "❌ ERROR: Folder Android ('aplikasi android' atau 'android-app') tidak ditemukan!" -ForegroundColor Red
            exit 1
        }
    }
}

$gradleFile = Join-Path $androidDir "app\build.gradle.kts"
$keystoreFile = Join-Path $androidDir "mataverse-release-key.jks"
$signingPropsFile = Join-Path $androidDir "signing.properties"
$releasesDir = Join-Path $androidDir "releases"

if (-not (Test-Path $gradleFile)) {
    Write-Host "❌ ERROR: File build.gradle.kts tidak ditemukan di $gradleFile" -ForegroundColor Red
    exit 1
}

# Baca versi saat ini dari build.gradle.kts
$gradleContent = Get-Content -Path $gradleFile -Raw
$currentVersionCode = 1
$currentVersionName = "1.0.0"

if ($gradleContent -match 'versionCode\s*=\s*(\d+)') {
    $currentVersionCode = [int]$matches[1]
}
if ($gradleContent -match 'versionName\s*=\s*"([^"]+)"') {
    $currentVersionName = $matches[1]
}

# Evaluasi parameter jika kosong
if (-not $VersionName) {
    Write-Host "==================================================================" -ForegroundColor Yellow
    Write-Host "❌ ERROR: Anda wajib memasukkan versi APK sebagai parameter!" -ForegroundColor Red
    Write-Host "==================================================================" -ForegroundColor Yellow
    Write-Host "Versi saat ini di sistem : v$currentVersionName (versionCode: $currentVersionCode)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Cara Penggunaan:"
    Write-Host "  .\build-apk.ps1 <version_name> [version_code] [-Install]"
    Write-Host "  .\build-apk.bat <version_name> [version_code] [--install]"
    Write-Host ""
    Write-Host "Contoh:"
    Write-Host "  .\build-apk.ps1 1.0.1             # Otomatis menaikkan versionCode menjadi $($currentVersionCode + 1)"
    Write-Host "  .\build-apk.ps1 1.0.1 2           # Menentukan versionName=1.0.1 dan versionCode=2 secara manual"
    Write-Host "  .\build-apk.ps1 1.0.1 2 -Install  # Build lalu otomatis install ke HP / Emulator"
    Write-Host "  .\build-apk.ps1 1.0.1 --install   # Dukungan format flag bash"
    Write-Host "==================================================================" -ForegroundColor Yellow
    exit 1
}

$installToDevice = $Install.IsPresent
$newVersionName = $VersionName
$newVersionCode = ""

# Handle argumen posisi ke-2 dan ke-3 jika memakai sintaks CLI bash
if ($VersionCodeOrFlag -eq "--install" -or $VersionCodeOrFlag -eq "-install" -or $VersionCodeOrFlag -eq "-Install") {
    $installToDevice = $true
} elseif ($VersionCodeOrFlag) {
    $newVersionCode = $VersionCodeOrFlag
}

if ($ExtraFlag -eq "--install" -or $ExtraFlag -eq "-install" -or $ExtraFlag -eq "-Install") {
    $installToDevice = $true
}

# Validasi format Semantic Versioning (X.Y.Z)
if ($newVersionName -notmatch '^\d+\.\d+\.\d+$') {
    Write-Host "❌ ERROR: Format version_name '$newVersionName' tidak valid!" -ForegroundColor Red
    Write-Host "   Gunakan format angka X.Y.Z (contoh: 1.0.0, 1.0.1, 1.1.0, 2.0.0)" -ForegroundColor Yellow
    exit 1
}

# Tentukan versionCode otomatis jika tidak diisi
if (-not $newVersionCode) {
    if ($newVersionName -eq $currentVersionName) {
        $newVersionCode = $currentVersionCode
    } else {
        $newVersionCode = $currentVersionCode + 1
    }
} else {
    $newVersionCode = [int]$newVersionCode
}

# Validasi versionCode harus angka bulat positif
if ($newVersionCode -lt 1) {
    Write-Host "❌ ERROR: version_code '$newVersionCode' harus berupa angka bulat positif!" -ForegroundColor Red
    exit 1
}

Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host "🚀 MATAVERSE HUB - PRODUCTION APK BUILDER" -ForegroundColor Green
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host "📌 Versi Sebelumnya : v$currentVersionName (Code: $currentVersionCode)"
Write-Host "✨ Versi Target     : v$newVersionName (Code: $newVersionCode)"
Write-Host "📲 Install Target   : $(if ($installToDevice) { 'YA (ADB/Device)' } else { 'TIDAK' })"
Write-Host "==================================================================" -ForegroundColor Cyan

# 1. Pastikan Keystore & signing.properties tersedia
if (-not (Test-Path $keystoreFile)) {
    Write-Host "🔐 Keystore belum ditemukan. Mencari keytool untuk generate mataverse-release-key.jks..." -ForegroundColor Yellow
    
    $keytoolPath = "keytool"
    if (-not (Get-Command "keytool" -ErrorAction SilentlyContinue)) {
        $candidatePaths = @(
            "$env:JAVA_HOME\bin\keytool.exe",
            "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe",
            "C:\Program Files\Java\jdk*\bin\keytool.exe"
        )
        foreach ($cp in $candidatePaths) {
            $found = Get-Item $cp -ErrorAction SilentlyContinue | Select-Object -First 1
            if ($found) {
                $keytoolPath = $found.FullName
                break
            }
        }
    }

    Write-Host "   Menggunakan keytool: $keytoolPath" -ForegroundColor DarkGray
    & $keytoolPath -genkey -v `
        -keystore "$keystoreFile" `
        -keyalg RSA `
        -keysize 2048 `
        -validity 10000 `
        -alias mataverse-key `
        -storepass mataverse2026 `
        -keypass mataverse2026 `
        -dname "CN=Mataverse Hub, OU=Engineering, O=PT Mataverse, L=Denpasar, ST=Bali, C=ID"
}

# Pastikan signing.properties ada agar Gradle mem-build Signed Release APK
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
if (-not (Test-Path $signingPropsFile)) {
    Write-Host "📝 Membuat signing.properties konfigurasi Gradle..." -ForegroundColor DarkGray
    $signingContent = @"
storeFile=mataverse-release-key.jks
storePassword=mataverse2026
keyAlias=mataverse-key
keyPassword=mataverse2026
"@
    [System.IO.File]::WriteAllText($signingPropsFile, $signingContent, $utf8NoBom)
}

# 2. Update versionCode & versionName di build.gradle.kts
Write-Host "📝 Memperbarui versi di app/build.gradle.kts..." -ForegroundColor Cyan
$updatedGradle = $gradleContent -replace 'versionCode\s*=\s*\d+', "versionCode = $newVersionCode"
$updatedGradle = $updatedGradle -replace 'versionName\s*=\s*"[^"]+"', "versionName = `"$newVersionName`""
[System.IO.File]::WriteAllText($gradleFile, $updatedGradle, $utf8NoBom)

# 3. Jalankan Gradle assembleRelease
Write-Host "⚙️  Menjalankan gradlew.bat assembleRelease..." -ForegroundColor Cyan

# Set JAVA_HOME jika belum ada atau gunakan JBR bawaan Android Studio
if (-not $env:JAVA_HOME -or -not (Test-Path "$env:JAVA_HOME\bin\javac.exe")) {
    $jbrPath = "C:\Program Files\Android\Android Studio\jbr"
    if (Test-Path $jbrPath) {
        $env:JAVA_HOME = $jbrPath
    }
}

Push-Location $androidDir
try {
    .\gradlew.bat --stop | Out-Null
    .\gradlew.bat assembleRelease --no-daemon
    if ($LASTEXITCODE -ne 0) {
        Write-Host "❌ ERROR: Gradle build gagal dengan exit code $LASTEXITCODE" -ForegroundColor Red
        exit $LASTEXITCODE
    }
} finally {
    Pop-Location
}

$apkSource = Join-Path $androidDir "app\build\outputs\apk\release\app-release.apk"
if (-not (Test-Path $apkSource)) {
    # Cek unsigned fallback
    $apkSourceUnsigned = Join-Path $androidDir "app\build\outputs\apk\release\app-release-unsigned.apk"
    if (Test-Path $apkSourceUnsigned) {
        $apkSource = $apkSourceUnsigned
    } else {
        Write-Host "❌ ERROR: File APK gagal ditemukan di $apkSource" -ForegroundColor Red
        exit 1
    }
}

# 4. Salin ke folder releases dengan nama versi yang rapi
if (-not (Test-Path $releasesDir)) {
    New-Item -ItemType Directory -Path $releasesDir -Force | Out-Null
}

$apkNamed = Join-Path $releasesDir "mataverse-hub-v$newVersionName-code$newVersionCode.apk"
Copy-Item -Path $apkSource -Destination $apkNamed -Force

# 5. Hitung Ukuran & SHA-256
$apkFileItem = Get-Item $apkSource
$apkBytes = $apkFileItem.Length
$apkSizeHuman = "{0:N2} MB" -f ($apkBytes / 1MB)
$apkSha256 = (Get-FileHash -Path $apkSource -Algorithm SHA256).Hash.ToLower()

Write-Host ""
Write-Host "==================================================================" -ForegroundColor Green
Write-Host "✅ BUILD PRODUCTION APK BERHASIL!" -ForegroundColor Green
Write-Host "==================================================================" -ForegroundColor Green
Write-Host "📦 Version Name     : $newVersionName"
Write-Host "🔢 Version Code     : $newVersionCode"
Write-Host "📏 Ukuran File      : $apkSizeHuman ($apkBytes bytes)"
Write-Host "🔒 SHA-256 Checksum : $apkSha256"
Write-Host "------------------------------------------------------------------"
Write-Host "📂 File APK Default : $apkSource"
Write-Host "📂 File APK Arsip   : $apkNamed"
Write-Host "==================================================================" -ForegroundColor Green

# 6. Opsional: Install langsung ke HP / Emulator via ADB jika flag -Install aktif
if ($installToDevice) {
    Write-Host "📲 Menginstal APK Release ke perangkat yang terhubung..." -ForegroundColor Cyan
    
    $adbPath = "adb"
    if (-not (Get-Command "adb" -ErrorAction SilentlyContinue)) {
        $candidateAdb = "$env:LOCALAPPDATA\Android\Sdk\platform-tools\adb.exe"
        if (Test-Path $candidateAdb) {
            $adbPath = $candidateAdb
        }
    }

    $oldEap = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $installOutput = & $adbPath install -r "$apkNamed" 2>&1 | Out-String
    if ($installOutput -match "INSTALL_FAILED_UPDATE_INCOMPATIBLE") {
        Write-Host "⚠️  Sertifikat berbeda (debug vs release terdeteksi). Menghapus versi lama terlebih dahulu..." -ForegroundColor Yellow
        & $adbPath uninstall id.webprint.bridge 2>&1 | Out-Null
        $installOutput = & $adbPath install -r "$apkNamed" 2>&1 | Out-String
    }
    $ErrorActionPreference = $oldEap

    if ($installOutput -match "Success") {
        Write-Host "✅ Instalasi APK ke perangkat selesai!" -ForegroundColor Green
        Write-Host "🚀 Menjalankan aplikasi di perangkat..." -ForegroundColor Cyan
        & $adbPath shell am start -n id.webprint.bridge/.MainActivity 2>&1 | Out-Null
    } else {
        Write-Host $installOutput -ForegroundColor Yellow
    }
}
