#!/bin/bash
# Lovački savez OBŽ — deploy na poslužitelju (pokreće se kao cPanel korisnik, NE kao root):
#   bash ~/lovacki/deploy.sh                    — git pull + web/ → ~/public_html (samo kod)
#   bash ~/lovacki/deploy.sh --paket ~/lovacki-podaci.tgz
#                                               — prvi put: + uploads/, images/ i baza iz paketa (_alat/paket.sh)
#
# Ne dira: uploads/ i images/ (slike), data/, inc/db-config.php i inc/mail-config.php (tajne),
# .well-known/, DCV datoteke i old/ (stara Joomla). Baza (MySQL lovacki_new) se uvozi SAMO s --paket i prepisuje sav sadržaj.
set -euo pipefail

REPO="$HOME/lovacki"
WEB="$HOME/public_html"
PAKET=""
if [ "${1:-}" = "--paket" ]; then PAKET="${2:?navedi putanju do paketa}"; fi

cd "$REPO"
git pull --ff-only

rsync -a --delete \
  --exclude 'uploads/' \
  --exclude 'images/' \
  --exclude 'data/' \
  --exclude 'inc/db-config.php' \
  --exclude 'inc/mail-config.php' \
  --exclude '.well-known/' \
  --exclude 'cgi-bin/' \
  --exclude 'error_log' \
  --exclude '*.txt' \
  --exclude '*.cpaneldcv' \
  "$REPO/web/" "$WEB/"
cp "$REPO/web/robots.txt" "$WEB/robots.txt"

# mape koje PHP mora moći pisati + zaštite (u uploads/ i images/ se PHP ne izvršava, data/ i inc/ nisu dostupni)
mkdir -p "$WEB/uploads" "$WEB/images" "$WEB/data"
cp "$REPO/web/uploads/.htaccess" "$WEB/uploads/.htaccess"
cp "$REPO/web/uploads/.htaccess" "$WEB/images/.htaccess"
cp "$REPO/web/data/.htaccess" "$WEB/data/.htaccess"
chmod 775 "$WEB/uploads" "$WEB/data"

if [ -n "$PAKET" ]; then
  [ -f "$WEB/inc/db-config.php" ] || { echo "✗ nema $WEB/inc/db-config.php — prvo ga postavi (vidi README)"; exit 1; }
  TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
  tar -xzf "$PAKET" -C "$TMP" --no-same-owner
  echo "› slike: uploads/ i images/"
  [ -d "$TMP/uploads" ] && rsync -a "$TMP/uploads/" "$WEB/uploads/"
  [ -d "$TMP/images" ] && rsync -a "$TMP/images/" "$WEB/images/"
  if [ -f "$TMP/lovacki-baza.sql" ]; then
    echo "› baza: uvoz u MySQL (prepisuje sadržaj)"
    # podaci za spajanje iz inc/db-config.php, u privremeni .my.cnf (lozinka ne ide u naredbeni redak)
    php -r '$c = include $argv[1]; echo "[client]\nhost=", ($c["host"] ?? "localhost") === "localhost" ? "127.0.0.1" : $c["host"], "\nuser=", $c["korisnik"], "\npassword=\"", addcslashes($c["lozinka"], "\"\\"), "\"\ndefault-character-set=utf8mb4\n";' "$WEB/inc/db-config.php" > "$TMP/my.cnf"
    chmod 600 "$TMP/my.cnf"
    BAZA="$(php -r '$c = include $argv[1]; echo $c["baza"];' "$WEB/inc/db-config.php")"
    mysql --defaults-extra-file="$TMP/my.cnf" "$BAZA" < "$TMP/lovacki-baza.sql"
    echo "  zapisa: $(mysql --defaults-extra-file="$TMP/my.cnf" -N "$BAZA" -e 'SELECT COUNT(*) FROM sadrzaj')"
  fi
  chmod -R u+rwX,g+rwX "$WEB/uploads"
fi

echo "✓ Deploy gotov: $(git log -1 --format='%h %s')"
