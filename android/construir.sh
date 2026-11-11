#!/bin/bash
# Construye y firma LuniCitas.apk sin Android Studio (Ubuntu: apt install aapt apksigner zipalign dalvik-exchange android-sdk-platform-23).
# Uso: ./construir.sh /ruta/llave.jks /ruta/archivo_con_la_clave   → deja el APK en ../descargas/LuniCitas.apk
set -euo pipefail
cd "$(dirname "$0")"
LLAVE="$1"; CLAVE="$2"
JAR=/usr/lib/android-sdk/platforms/android-23/android.jar
[ -f "$JAR" ] || JAR=$(ls /usr/lib/android-sdk/platforms/*/android.jar | head -1)
T=$(mktemp -d); trap 'rm -rf "$T"' EXIT
mkdir -p "$T/gen" "$T/clases" ../descargas
aapt package -f -m -J "$T/gen" -M AndroidManifest.xml -S res -I "$JAR"
javac -nowarn -Xlint:-options -source 8 -target 8 -bootclasspath "$JAR" -d "$T/clases" $(find src "$T/gen" -name '*.java')
dalvik-exchange --dex --output="$T/classes.dex" "$T/clases"
aapt package -f -M AndroidManifest.xml -S res -I "$JAR" -F "$T/base.apk"
(cd "$T" && aapt add base.apk classes.dex >/dev/null)
zipalign -f -p 4 "$T/base.apk" "$T/alineado.apk"
apksigner sign --ks "$LLAVE" --ks-key-alias lunicitas --ks-pass "file:$CLAVE" --out ../descargas/LuniCitas.apk "$T/alineado.apk"
rm -f ../descargas/LuniCitas.apk.idsig
apksigner verify --print-certs ../descargas/LuniCitas.apk | grep -E "SHA-256|Signer" || true
ls -l ../descargas/LuniCitas.apk
