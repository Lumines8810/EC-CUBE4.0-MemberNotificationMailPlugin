#!/usr/bin/env bash
set -euo pipefail

PLUGIN_CODE="CustomerChangeNotify"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
DIST_DIR="${BUILD_DIR}/${PLUGIN_CODE}"

rm -rf "${BUILD_DIR}"
mkdir -p "${DIST_DIR}"

rsync -a \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='.idea' \
  --exclude='.vscode' \
  --exclude='vendor' \
  --exclude='tests' \
  --exclude='build' \
  --exclude='.claude' \
  --exclude='AGENTS.md' \
  --exclude='CLAUDE.md' \
  --exclude='composer.lock' \
  --exclude='phpunit.xml' \
  --exclude='phpunit.xml.dist' \
  --exclude='coverage' \
  "${ROOT_DIR}/" "${DIST_DIR}/"

(
  cd "${BUILD_DIR}"
  zip -r "${PLUGIN_CODE}.zip" "${PLUGIN_CODE}" >/dev/null
)

echo "✅ Build completed: ${BUILD_DIR}/${PLUGIN_CODE}.zip"
