#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
ZIP_NAME="${1:-iforum-php-template.zip}"

mkdir -p "$DIST_DIR"
rm -f "$DIST_DIR/$ZIP_NAME"

cd "$ROOT_DIR"
zip -r "$DIST_DIR/$ZIP_NAME" . \
  -x "dist/*" \
  -x "packages/*" \
  -x ".git/*" \
  -x "app/config.php" \
  -x "app/installed.lock" \
  -x "uploads/*" \
  -x ".DS_Store" \
  -x "*.log"

echo "Created $DIST_DIR/$ZIP_NAME"
