#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

python - <<'PY'
from pathlib import Path
import zipfile

root = Path.cwd()
plugin = root / 'woo-delivery-scheduler'
out = root / 'woo-delivery-scheduler.zip'

if not plugin.exists():
    raise SystemExit('Plugin folder not found: ' + str(plugin))

if out.exists():
    out.unlink()

with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as zf:
    for p in plugin.rglob('*'):
        if p.is_file() and p.name != '.DS_Store':
            zf.write(p, p.relative_to(root))

print(out)
PY

echo "Built: $ROOT_DIR/woo-delivery-scheduler.zip"
