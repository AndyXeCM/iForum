#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${1:-8080}"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP is not installed or not in PATH." >&2
  exit 1
fi

cd "$ROOT_DIR"
php -S "127.0.0.1:$PORT"

