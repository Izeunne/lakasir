#!/usr/bin/env bash
# ==============================================================================
# Mataverse Hub - Automated Production APK Builder (macOS / Linux / Bash)
# Usage:
#   ./build-apk.sh <version_name> [version_code] [--install]
# Examples:
#   ./build-apk.sh 1.0.4
#   ./build-apk.sh 1.0.4 5
#   ./build-apk.sh 1.0.4 5 --install
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ANDROID_DIR="$SCRIPT_DIR/aplikasi android"

if [[ ! -d "$ANDROID_DIR" ]]; then
  if [[ -f "$SCRIPT_DIR/app/build.gradle.kts" ]]; then
    ANDROID_DIR="$SCRIPT_DIR"
  else
    echo "❌ ERROR: Folder Android ('aplikasi android') tidak ditemukan!"
    exit 1
  fi
fi

GRADLE_FILE="$ANDROID_DIR/app/build.gradle.kts"
KEYSTORE_FILE="$ANDROID_DIR/mataverse-release-key.jks"
SIGNING_PROPS_FILE="$ANDROID_DIR/signing.properties"
RELEASES_DIR="$ANDROID_DIR/releases"

if [[ ! -f "$GRADLE_FILE" ]]; then
  echo "❌ ERROR: File build.gradle.kts tidak ditemukan di $GRADLE_FILE"
  exit 1
fi

CURRENT_VERSION_CODE=$(grep -E 'versionCode\s*=\s*[0-9]+' "$GRADLE_FILE" | head -n 1 | sed -E 's/.*=[[:space:]]*([0-9]+).*/\1/' | tr -d '[:space:]')
CURRENT_VERSION_NAME=$(grep -E 'versionName\s*=\s*"[^"]+"' "$GRADLE_FILE" | head -n 1 | sed -E 's/.*"([^"]+)".*/\1/' | tr -d '[:space:]')

VERSION_NAME="${1:-}"
VERSION_CODE_OR_FLAG="${2:-}"
EXTRA_FLAG="${3:-}"

INSTALL_TO_DEVICE=false
if [[ "$VERSION_CODE_OR_FLAG" == "--install" || "$VERSION_CODE_OR_FLAG" == "-install" ]]; then
  INSTALL_TO_DEVICE=true
  NEW_VERSION_CODE=""
else
  NEW_VERSION_CODE="$VERSION_CODE_OR_FLAG"
fi

if [[ "$EXTRA_FLAG" == "--install" || "$EXTRA_FLAG" == "-install" ]]; then
  INSTALL_TO_DEVICE=true
fi

if [[ -z "$VERSION_NAME" ]]; then
  echo "=================================================================="
  echo "❌ ERROR: Anda wajib memasukkan versi APK sebagai parameter!"
  echo "=================================================================="
  echo "Versi saat ini di sistem : v$CURRENT_VERSION_NAME (versionCode: $CURRENT_VERSION_CODE)"
  echo ""
  echo "Cara Penggunaan:"
  echo "  ./build-apk.sh <version_name> [version_code] [--install]"
  echo ""
  echo "Contoh:"
  echo "  ./build-apk.sh 1.0.4            # Otomatis menaikkan versionCode menjadi $((CURRENT_VERSION_CODE + 1))"
  echo "  ./build-apk.sh 1.0.4 5          # Menentukan versionName=1.0.4 dan versionCode=5 secara manual"
  echo "  ./build-apk.sh 1.0.4 5 --install # Build lalu otomatis install ke HP / Emulator via ADB"
  echo "=================================================================="
  exit 1
fi

if [[ ! "$VERSION_NAME" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "❌ ERROR: Format version_name '$VERSION_NAME' tidak valid! Gunakan format angka X.Y.Z (contoh: 1.0.4)"
  exit 1
fi

if [[ -z "$NEW_VERSION_CODE" ]]; then
  if [[ "$VERSION_NAME" == "$CURRENT_VERSION_NAME" ]]; then
    NEW_VERSION_CODE="$CURRENT_VERSION_CODE"
  else
    NEW_VERSION_CODE=$((CURRENT_VERSION_CODE + 1))
  fi
fi

# Pastikan JAVA_HOME dan ANDROID_HOME terpasang
if [[ -z "${JAVA_HOME:-}" || ! -x "${JAVA_HOME}/bin/java" ]]; then
  if [[ -d "/Applications/Android Studio.app/Contents/jbr/Contents/Home" ]]; then
    export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
  fi
fi

if [[ -z "${ANDROID_HOME:-}" || ! -d "${ANDROID_HOME}" ]]; then
  if [[ -d "$HOME/Library/Android/sdk" ]]; then
    export ANDROID_HOME="$HOME/Library/Android/sdk"
  fi
fi

KEYTOOL="keytool"
if ! command -v keytool &> /dev/null; then
  if [[ -x "${JAVA_HOME:-}/bin/keytool" ]]; then
    KEYTOOL="${JAVA_HOME}/bin/keytool"
  fi
fi

echo "=================================================================="
echo "🚀 MATAVERSE HUB - PRODUCTION APK BUILDER (macOS / Linux)"
echo "=================================================================="
echo "📌 Versi Sebelumnya : v$CURRENT_VERSION_NAME (Code: $CURRENT_VERSION_CODE)"
echo "✨ Versi Target     : v$VERSION_NAME (Code: $NEW_VERSION_CODE)"
echo "📲 Install Target   : $(if [[ "$INSTALL_TO_DEVICE" == true ]]; then echo 'YA (ADB/Device)'; else echo 'TIDAK'; fi)"
echo "☕ JAVA_HOME        : ${JAVA_HOME:-default}"
echo "📱 ANDROID_HOME     : ${ANDROID_HOME:-default}"
echo "=================================================================="

# 1. Pastikan Keystore & signing.properties tersedia
if [[ ! -f "$KEYSTORE_FILE" ]]; then
  echo "🔐 Keystore belum ditemukan. Membuat $KEYSTORE_FILE..."
  "$KEYTOOL" -genkeypair -v \
    -keystore "$KEYSTORE_FILE" \
    -keyalg RSA \
    -keysize 2048 \
    -validity 10000 \
    -alias mataverse-key \
    -storepass mataverse2026 \
    -keypass mataverse2026 \
    -dname "CN=Mataverse Hub, OU=Engineering, O=PT Mataverse, L=Denpasar, ST=Bali, C=ID"
fi

if [[ ! -f "$SIGNING_PROPS_FILE" ]]; then
  echo "📝 Membuat signing.properties konfigurasi Gradle..."
  cat <<EOF > "$SIGNING_PROPS_FILE"
storeFile=mataverse-release-key.jks
storePassword=mataverse2026
keyAlias=mataverse-key
keyPassword=mataverse2026
EOF
fi

# Pastikan keystore dan signing.properties juga tersedia di subfolder app
cp -f "$KEYSTORE_FILE" "$ANDROID_DIR/app/mataverse-release-key.jks"
cp -f "$SIGNING_PROPS_FILE" "$ANDROID_DIR/app/signing.properties"

# 2. Update versionCode & versionName di build.gradle.kts
echo "📝 Memperbarui versi di app/build.gradle.kts..."
sed -i '' -E "s/versionCode = [0-9]+/versionCode = $NEW_VERSION_CODE/" "$GRADLE_FILE"
sed -i '' -E "s/versionName = \"[^\"]+\"/versionName = \"$VERSION_NAME\"/" "$GRADLE_FILE"

# 3. Jalankan Gradle assembleRelease
echo "⚙️  Menjalankan ./gradlew assembleRelease --no-daemon..."
cd "$ANDROID_DIR"
chmod +x ./gradlew
./gradlew --stop || true
./gradlew assembleRelease --no-daemon

APK_SOURCE="$ANDROID_DIR/app/build/outputs/apk/release/app-release.apk"
if [[ ! -f "$APK_SOURCE" ]]; then
  APK_UNSIGNED="$ANDROID_DIR/app/build/outputs/apk/release/app-release-unsigned.apk"
  if [[ -f "$APK_UNSIGNED" ]]; then
    APK_SOURCE="$APK_UNSIGNED"
  else
    echo "❌ ERROR: File APK gagal ditemukan di $APK_SOURCE"
    exit 1
  fi
fi

# 4. Salin ke folder releases & public/downloads
mkdir -p "$RELEASES_DIR"
APK_NAMED="$RELEASES_DIR/mataverse-hub-v$VERSION_NAME-code$NEW_VERSION_CODE.apk"
cp -f "$APK_SOURCE" "$APK_NAMED"

PUBLIC_DOWNLOADS="$SCRIPT_DIR/public/downloads"
if [[ -d "$PUBLIC_DOWNLOADS" ]]; then
  cp -f "$APK_SOURCE" "$PUBLIC_DOWNLOADS/lakasir-bridge.apk"
fi

# 5. Informasi hasil build
APK_BYTES=$(stat -f%z "$APK_SOURCE" 2>/dev/null || stat -c%s "$APK_SOURCE")
APK_MB=$(awk "BEGIN {printf \"%.2f\", $APK_BYTES/1048576}")
APK_SHA=$(shasum -a 256 "$APK_SOURCE" | awk '{print $1}')

echo ""
echo "=================================================================="
echo "✅ BUILD PRODUCTION APK BERHASIL!"
echo "=================================================================="
echo "📦 Version Name     : $VERSION_NAME"
echo "🔢 Version Code     : $NEW_VERSION_CODE"
echo "📏 Ukuran File      : ${APK_MB} MB ($APK_BYTES bytes)"
echo "🔒 SHA-256 Checksum : $APK_SHA"
echo "------------------------------------------------------------------"
echo "📂 File APK Default : $APK_SOURCE"
echo "📂 File APK Arsip   : $APK_NAMED"
if [[ -d "$PUBLIC_DOWNLOADS" ]]; then
  echo "🌐 Download Web     : $PUBLIC_DOWNLOADS/lakasir-bridge.apk"
fi
echo "=================================================================="

# 6. Install ke device jika flag --install aktif
if [[ "$INSTALL_TO_DEVICE" == true ]]; then
  ADB="adb"
  if ! command -v adb &> /dev/null; then
    if [[ -x "${ANDROID_HOME:-}/platform-tools/adb" ]]; then
      ADB="${ANDROID_HOME}/platform-tools/adb"
    fi
  fi

  echo "📲 Menginstal APK Release ke perangkat yang terhubung..."
  INSTALL_OUTPUT=$("$ADB" install -r "$APK_NAMED" 2>&1 || true)
  if echo "$INSTALL_OUTPUT" | grep -q "INSTALL_FAILED_UPDATE_INCOMPATIBLE"; then
    echo "⚠️  Sertifikat berbeda. Menghapus versi lama terlebih dahulu..."
    "$ADB" uninstall id.webprint.bridge || true
    INSTALL_OUTPUT=$("$ADB" install -r "$APK_NAMED" 2>&1 || true)
  fi

  if echo "$INSTALL_OUTPUT" | grep -q "Success"; then
    echo "✅ Instalasi APK ke perangkat selesai!"
    echo "🚀 Menjalankan aplikasi di perangkat..."
    "$ADB" shell am start -n id.webprint.bridge/.MainActivity || true
  else
    echo "$INSTALL_OUTPUT"
  fi
fi
