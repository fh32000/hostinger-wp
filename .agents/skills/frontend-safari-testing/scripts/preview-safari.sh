#!/usr/bin/env bash
# Safari / WebKit preview runner on Linux
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
URL="${1:-http://localhost:3000}"
export WEBKIT_DISABLE_SANDBOX_THIS_IS_DANGEROUS=1

echo "=================================================="
echo "  Safari / WebKit Engine Preview on Linux"
echo "  URL: $URL"
echo "=================================================="

# Check if user specifically requested Epiphany
if [[ "$*" == *"--epiphany"* ]]; then
  if command -v flatpak >/dev/null 2>&1 && flatpak info org.gnome.Epiphany >/dev/null 2>&1; then
    echo "Opening Epiphany (GNOME Web)..."
    flatpak run org.gnome.Epiphany --new-window "$URL" >/dev/null 2>&1 &
    exit 0
  fi
fi

# Primary runner: Native WebKit 6.0 GTK app with iPhone/iPad/Desktop presets & Web Inspector
if python3 -c "import gi; gi.require_version('WebKit', '6.0')" >/dev/null 2>&1; then
  echo "Launching Native WebKit 6.0 Safari Preview window..."
  echo "Features: iPhone 15 / iPad / Desktop switchers + WebKit Dev Inspector"
  nohup python3 "$DIR/safari_preview.py" "$@" >/dev/null 2>&1 & disown
elif command -v flatpak >/dev/null 2>&1 && flatpak info org.gnome.Epiphany >/dev/null 2>&1; then
  echo "Opening native WebKit browser (GNOME Web / Epiphany)..."
  flatpak run org.gnome.Epiphany --new-window "$URL" >/dev/null 2>&1 &
else
  echo "Fallback to Node runner..."
  node "$DIR/test-safari.mjs" "$@"
fi
