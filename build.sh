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
  --exclude='build.sh' \
  --exclude='Plugin.php' \
  --exclude='.DS_Store' \
  --exclude='Thumbs.db' \
  --exclude='.AppleDouble' \
  --exclude='.LSOverride' \
  --exclude='*.swp' \
  --exclude='*.swo' \
  --exclude='*~' \
  --exclude='*.tmp' \
  --exclude='*.bak' \
  --exclude='*.log' \
  --exclude='.phpunit.result.cache' \
  --exclude='node_modules' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  "${ROOT_DIR}/" "${DIST_DIR}/"

(
  cd "${DIST_DIR}"
  zip -r "../${PLUGIN_CODE}.zip" . >/dev/null
)

echo "✅ Build completed: ${BUILD_DIR}/${PLUGIN_CODE}.zip"
