#!/usr/bin/env bash
# Build a release zip of the plugin, ready to upload to a site or to wordpress.org.
#
# Usage: bin/build-release.sh            # from the plugin directory
#
# Composer's dev dependencies (Strauss pulls in composer/composer and more) are needed to
# prefix the library, but must not ship: zipping vendor/ as-is produces hundreds of megabytes.
# This script:
#   1. runs a full `composer install`, so Strauss prefixes the library into vendor-prefixed/
#   2. regenerates the autoloader with --no-dev --classmap-authoritative
#   3. copies only the runtime files: the plugin's own files, vendor-prefixed/,
#      vendor/autoload.php and the top-level files of vendor/composer/ (no subdirectories)
#   4. fails if any unprefixed reference to the library is left
#   5. zips the result into build/<slug>.zip
#
# Afterwards your working copy gets its normal development autoloader back.
set -euo pipefail

cd "$(dirname "$0")/.."

SLUG=$(basename "$PWD")
BUILD_DIR=build
OUT="$BUILD_DIR/$SLUG"
# The library's namespace before prefixing. Matching it anywhere in the build means Strauss
# didn't rewrite a reference, and the plugin would fatal (or load another plugin's copy).
UNPREFIXED='(^|[^\\A-Za-z0-9_])Bignall\\WPFoundation\\'
# Files and directories that make up the plugin at runtime. Add yours here.
RUNTIME_FILES=("$SLUG.php" src README.md)

echo "Installing dependencies (Strauss prefixes the library)..."
composer install --no-interaction --no-progress

echo "Generating the production autoloader..."
composer dump-autoload --no-dev --classmap-authoritative --no-interaction
# Whatever happens next, restore the development autoloader.
trap 'composer dump-autoload --no-interaction >/dev/null 2>&1 || true' EXIT

echo "Copying runtime files to $OUT..."
rm -rf "$BUILD_DIR"
mkdir -p "$OUT/vendor/composer"
for path in "${RUNTIME_FILES[@]}"; do
	if [ -e "$path" ]; then
		cp -R "$path" "$OUT/"
	fi
done
cp -R vendor-prefixed "$OUT/"
cp vendor/autoload.php "$OUT/vendor/"
# Only the autoloader's own files; subdirectories hold dev tools such as composer/composer.
find vendor/composer -maxdepth 1 -type f -exec cp {} "$OUT/vendor/composer/" \;

echo "Checking that the library is prefixed..."
if grep -rnE --include='*.php' "$UNPREFIXED" "$OUT"; then
	echo "ERROR: unprefixed Bignall\\WPFoundation references found above." >&2
	echo "Run: rm -rf vendor vendor-prefixed && composer install, then build again." >&2
	exit 1
fi
if ! grep -rqE --include='*.php' 'namespace [A-Za-z0-9_\\]+\\Bignall\\WPFoundation' "$OUT/vendor-prefixed"; then
	echo "ERROR: no prefixed copy of the library in vendor-prefixed/." >&2
	exit 1
fi

if ! find "$OUT/vendor-prefixed" -name 'deactivation-survey.js' | grep -q .; then
	echo "ERROR: the deactivation survey's script is missing from vendor-prefixed/." >&2
	exit 1
fi

echo "Checking PHP syntax..."
find "$OUT" -name '*.php' -print0 | xargs -0 -n1 -P4 php -l >/dev/null

if command -v zip >/dev/null; then
	(cd "$BUILD_DIR" && rm -f "$SLUG.zip" && zip -rq "$SLUG.zip" "$SLUG")
	echo "Built $BUILD_DIR/$SLUG.zip"
else
	echo "zip is not installed; the release is in $OUT"
fi
echo "$(find "$OUT" -type f | wc -l | tr -d ' ') files, $(du -sh "$OUT" | cut -f1)"
