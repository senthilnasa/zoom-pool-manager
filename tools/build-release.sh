#!/usr/bin/env sh
set -e

echo "=== Building Zoom Pool Manager (ZPM) Standalone Release ZIP ==="

ROOT_DIR="$(pwd)"
BUILD_DIR="dist"
VERSION_TAG="${1:-$(date +%Y%m%d%H%M%S)}"
RELEASE_NAME="zpm-release-${VERSION_TAG}"
TARGET_DIR="${BUILD_DIR}/${RELEASE_NAME}"

# Clean existing build directory
mkdir -p "${BUILD_DIR}"
rm -rf "${TARGET_DIR}"

echo "1. Copying project files..."
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

echo "2. Installing optimized production dependencies..."
cd "${TARGET_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "3. Creating release archive & checksum..."
cd "${ROOT_DIR}/${BUILD_DIR}"
zip -r "${RELEASE_NAME}.zip" "${RELEASE_NAME}"
rm -rf "${RELEASE_NAME}"

if command -v sha256sum > /dev/null 2>&1; then
    sha256sum "${RELEASE_NAME}.zip" > "${RELEASE_NAME}.zip.sha256"
elif command -v shasum > /dev/null 2>&1; then
    shasum -a 256 "${RELEASE_NAME}.zip" > "${RELEASE_NAME}.zip.sha256"
fi

echo "=== Release build complete: ${BUILD_DIR}/${RELEASE_NAME}.zip ==="
if [ -f "${RELEASE_NAME}.zip.sha256" ]; then
    echo "=== SHA256 Checksum: $(cat ${RELEASE_NAME}.zip.sha256) ==="
fi
