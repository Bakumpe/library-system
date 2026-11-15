#!/usr/bin/env bash
# Builds the submission zip, LMS_<group name>.zip, next to the project folder (not inside it).
# The zip holds exactly the brief's folder layout. The developer tools that GitHub needs (composer.json,
# .github/, .gitignore, .htaccess, .env.example) stay out, so nothing but index.php and README.md sits
# loose in the root. Secrets (.env) and Git history are never included.
# Usage: composer package -- "Group Sixteen"
set -eu
cd "$(dirname "$0")/../.." || exit 1

group=${1:-}
if [ -z "$group" ]; then
  echo 'Give the group name, for example: composer package -- "Group Sixteen"' >&2
  exit 1
fi
name="LMS_$(printf '%s' "$group" | tr -cs 'A-Za-z0-9' '_' | sed 's/^_//; s/_$//')"
out="$(cd .. && pwd)/$name.zip"

if [ ! -s docs/report.pdf ]; then
  echo "docs/report.pdf is missing or empty. Export the report to PDF first." >&2
  exit 1
fi

stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir "$stage/library-system"

# Every tracked or new file, minus ignored ones (.env, vendor) and the developer tools.
git ls-files -co --exclude-standard | while IFS= read -r f; do
  case "$f" in
    .*|*/.*|composer.json|docs/report.docx|docs/team.html) continue ;;
  esac
  [ -f "$f" ] || continue
  mkdir -p "$stage/library-system/$(dirname "$f")"
  cp "$f" "$stage/library-system/$f"
done
# Git does not keep empty folders, but the brief lists these.
mkdir -p "$stage/library-system/assets/images" "$stage/library-system/docs/screenshots"

rm -f "$out"
if command -v zip >/dev/null 2>&1; then
  (cd "$stage" && zip -qr "$out" library-system)
else
  # Git Bash on Windows has no zip command.
  powershell.exe -NoProfile -Command "Compress-Archive -Path '$(cygpath -w "$stage/library-system")' -DestinationPath '$(cygpath -w "$out")'"
fi

echo "Built $out"
echo "Root of the zip:"
ls -A "$stage/library-system" | sed 's/^/  /'
