#!/usr/bin/env bash
# Check a release zip the way Moodle's "Install plugin from ZIP file" does
# (core\update\validator): one root folder named after the plugin, and explicit
# directory entries, including <root>/lang/en/. Usage: check-zip.sh ZIP ROOT COMPONENT
# e.g. scripts/check-zip.sh local_bandedgrade_0.10.1.zip bandedgrade local_bandedgrade
set -euo pipefail

zip=${1:?zip file}
root=${2:?root folder, e.g. bandedgrade}
component=${3:?frankenstyle name, e.g. local_bandedgrade}

fail() { echo "FAIL: $*" >&2; exit 1; }

entries=$(unzip -Z1 "$zip")

roots=$(printf '%s\n' "$entries" | cut -d/ -f1 | sort -u)
[ "$roots" = "$root" ] || fail "root folder is '$roots', expected '$root' (rootinvalid)"

for entry in "$root/" "$root/lang/en/"; do
    printf '%s\n' "$entries" | grep -qxF "$entry" \
        || fail "no directory entry '$entry' (missinglangenfolder); build with git archive or zip, not a tool that skips directories"
done

# Activity modules name the file after the module (peerreview.php), other types after the component.
printf '%s\n' "$entries" | grep -qxE "$root/lang/en/($component|$root)\.php" || fail "missing $root/lang/en/$component.php"
printf '%s\n' "$entries" | grep -qxF "$root/version.php" || fail "missing $root/version.php"

echo "OK: $zip ($(printf '%s\n' "$entries" | grep -c '/$') directory entries)"
