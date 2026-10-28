#!/bin/bash
# Na poslužitelju: sadržaj izravno iz stare Joomle (baza iz njenog configuration.php, sve slike i PDF-ovi
# iz images/ i media/k2/), bez uploada paketa. Prepisuje bazu lovacki_new i uploads/ — samo prije objave!
#   bash ~/lovacki/_alat/uvoz-posluzitelj.sh [~/old]
set -euo pipefail

OLD="${1:-$HOME/old}"
REPO="$HOME/lovacki"
WEB="$HOME/public_html"

[ -f "$OLD/configuration.php" ] || { echo "✗ nema $OLD/configuration.php"; exit 1; }
[ -f "$WEB/inc/db-config.php" ] || { echo "✗ nema $WEB/inc/db-config.php"; exit 1; }
for m in pdo_sqlite pdo_mysql gd dom; do
  php -m | grep -qix "$m" || { echo "✗ PHP (CLI) nema modul $m"; exit 1; }
done

cd "$REPO"
git pull --ff-only

echo "› uvoz iz $OLD (slike se smanjuju — potraje nekoliko minuta)"
LSO_JOOMLA="$OLD" php -d memory_limit=1024M _alat/uvoz.php
php _alat/korisnik.php savez

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
echo "› baza → MySQL"
php _alat/izvoz-mysql.php > "$TMP/baza.sql"
php -r '$c = include $argv[1]; echo "[client]\nhost=", ($c["host"] ?? "localhost") === "localhost" ? "127.0.0.1" : $c["host"], "\nuser=", $c["korisnik"], "\npassword=\"", addcslashes($c["lozinka"], "\"\\"), "\"\ndefault-character-set=utf8mb4\n";' "$WEB/inc/db-config.php" > "$TMP/my.cnf"
chmod 600 "$TMP/my.cnf"
BAZA="$(php -r '$c = include $argv[1]; echo $c["baza"];' "$WEB/inc/db-config.php")"
mysql --defaults-extra-file="$TMP/my.cnf" "$BAZA" < "$TMP/baza.sql"
echo "  zapisa: $(mysql --defaults-extra-file="$TMP/my.cnf" -N "$BAZA" -e 'SELECT COUNT(*) FROM sadrzaj'), slika u galerijama: $(mysql --defaults-extra-file="$TMP/my.cnf" -N "$BAZA" -e 'SELECT COUNT(*) FROM galerija')"
rm -f "$REPO/web/data/lovacki.sqlite"* # lokalna SQLite kopija nije potrebna; izvor istine je MySQL

echo "› slike → public_html"
mkdir -p "$WEB/uploads" "$WEB/images"
rsync -a --delete --exclude '.htaccess' "$REPO/web/uploads/" "$WEB/uploads/"
rsync -a "$REPO/web/images/" "$WEB/images/"

echo "› kod"
bash "$REPO/deploy.sh"

echo "✓ Gotovo. Admin pristup: $REPO/_alat/admin-pristup.txt (pročitati, poslati klijentu, obrisati)"
grep -A40 'Nedostaje' "$REPO/_alat/uvoz.log" | head -15 || true
