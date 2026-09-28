#!/usr/bin/env sh
set -e

echo "=== Building Zoom Pool Manager (ZPM) Standalone Release Package ==="

ROOT_DIR="$(pwd)"
BUILD_DIR="dist"

# Determine version
if [ -n "$1" ]; then
    VERSION_TAG="$1"
elif [ -f "version.json" ]; then
    VERSION_TAG=$(php -r '$v=json_decode(file_get_contents("version.json"), true); echo $v["version"] ?? date("YmdHis");')
else
    VERSION_TAG="$(date +%Y%m%d%H%M%S)"
fi

# Clean tag (strip leading v)
CLEAN_VERSION=$(echo "$VERSION_TAG" | sed 's/^v//')
PACKAGE_NAME="zoom-pool-manager-v${CLEAN_VERSION}"
TARGET_DIR="${BUILD_DIR}/${PACKAGE_NAME}"

# Clean existing build directory
mkdir -p "${BUILD_DIR}"
rm -rf "${TARGET_DIR}"
rm -f "${BUILD_DIR}/${PACKAGE_NAME}.zip" "${BUILD_DIR}/${PACKAGE_NAME}.zip.sha256"
rm -f "${BUILD_DIR}/zpm-release-${VERSION_TAG}.zip" "${BUILD_DIR}/zpm-release-${VERSION_TAG}.zip.sha256"

echo "1. Copying project files into ${TARGET_DIR}..."
mkdir -p "${TARGET_DIR}"

rsync -av --exclude='.git' \
          --exclude='.github' \
          --exclude='tests' \
          --exclude='docker' \
          --exclude='dist' \
          --exclude='tools' \
          --exclude='ai' \
          --exclude='.ai' \
          --exclude='.env' \
          --exclude='node_modules' \
          --exclude='.phpunit.result.cache' \
          --exclude='storage/*.lock' \
          --exclude='storage/app/backups/*' \
          --exclude='storage/app/updates/*' \
          --exclude='storage/logs/*.log' \
          --exclude='storage/framework/cache/data/*' \
          --exclude='storage/framework/sessions/*' \
          --exclude='storage/framework/views/*' \
          ./ "${TARGET_DIR}/"

# Write metadata version.json
COMMIT_SHA=$(git rev-parse HEAD 2>/dev/null || echo "release")
RELEASE_DATE=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

cat <<EOF > "${TARGET_DIR}/version.json"
{
  "version": "${CLEAN_VERSION}",
  "build": "${COMMIT_SHA}",
  "release_date": "${RELEASE_DATE}",
  "php_min": "8.2.0"
}
EOF

echo "2. Installing optimized production dependencies..."
cd "${TARGET_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "3. Creating release archive & checksum..."
cd "${ROOT_DIR}/${BUILD_DIR}"

# Archive package
zip -r "${PACKAGE_NAME}.zip" "${PACKAGE_NAME}"
# Also create alias with zpm-release prefix for backward-compatibility
cp "${PACKAGE_NAME}.zip" "zpm-release-${VERSION_TAG}.zip"

# Remove intermediate directory
rm -rf "${PACKAGE_NAME}"

# Generate SHA256 checksums
if command -v sha256sum > /dev/null 2>&1; then
    sha256sum "${PACKAGE_NAME}.zip" > "${PACKAGE_NAME}.zip.sha256"
    sha256sum "zpm-release-${VERSION_TAG}.zip" > "zpm-release-${VERSION_TAG}.zip.sha256"
elif command -v shasum > /dev/null 2>&1; then
    shasum -a 256 "${PACKAGE_NAME}.zip" > "${PACKAGE_NAME}.zip.sha256"
    shasum -a 256 "zpm-release-${VERSION_TAG}.zip" > "zpm-release-${VERSION_TAG}.zip.sha256"
fi

echo "=== Release build complete: ==="
echo "  → ${BUILD_DIR}/${PACKAGE_NAME}.zip"
echo "  → ${BUILD_DIR}/zpm-release-${VERSION_TAG}.zip"
if [ -f "${PACKAGE_NAME}.zip.sha256" ]; then
    echo "=== SHA256 Checksum: $(cat ${PACKAGE_NAME}.zip.sha256) ==="
fi
