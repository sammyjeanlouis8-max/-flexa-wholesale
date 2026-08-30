#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARTS="$ROOT/hosting-assets"
restore_archive() {
  local archive="$1"
  local target="$2"
  local temp
  temp="$(mktemp)"
  find "$PARTS" -maxdepth 1 -type f -name "${archive}.part-*" -print0 | sort -z | xargs -0 cat > "$temp"
  rm -rf "$ROOT/$target"
  tar -xzf "$temp" -C "$ROOT"
  rm -f "$temp"
}
restore_archive public-back.tar.gz public/back
restore_archive public-dropify.tar.gz public/dropify
restore_archive public-uploads.tar.gz public/uploads
echo "FLEXA WHOLESALE hosting assets restored."
