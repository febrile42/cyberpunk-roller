#!/bin/sh
# SPDX-License-Identifier: PolyForm-Noncommercial-1.0.0
# cyberpunk-roller — shared host deploy
#
# Downloads only the files required to run the app (no Docker, tests, or
# development artifacts). Requires curl and PHP 8.0+ with pdo_sqlite.
#
# Usage:
#   sh deploy.sh [destination-dir]
#
# Or pipe directly (inspect first if preferred):
#   curl -fsSL https://raw.githubusercontent.com/febrile42/cyberpunk-roller/master/deploy.sh | sh

set -e

VERSION="1.2.0"
RELEASE_URL="https://github.com/febrile42/cyberpunk-roller/releases/tag/v${VERSION}"
BASE="https://raw.githubusercontent.com/febrile42/cyberpunk-roller/master"
DEST="${1:-.}"

echo "cyberpunk-roller v${VERSION}"
echo "  release : ${RELEASE_URL}"
echo "  dest    : ${DEST}"
echo ""

fetch() {
    printf "  fetching %s ... " "$1"
    curl -fsSL "$BASE/$1" -o "$DEST/$1"
    echo "ok"
}

mkdir -p \
    "$DEST/api" \
    "$DEST/src" \
    "$DEST/js"  \
    "$DEST/css" \
    "$DEST/data"

chmod 777 "$DEST/data"

fetch index.php
fetch .htaccess
fetch LICENSE
fetch api/roll.php
fetch api/events.php
fetch src/dice.php
fetch src/combat.php
fetch src/armor.php
fetch src/fire_log.php
fetch src/rate_limit.php
fetch src/db.php
fetch js/app.js
fetch css/style.css

echo ""
echo "deployed v${VERSION} to: ${DEST}"
