#!/bin/bash
# Lokalno: paket podataka za prvi deploy (ili ponovni uvoz nakon novih slika):
#   bash _alat/paket.sh        → _paket/lovacki-podaci.tgz  (web/uploads, web/images, MySQL dump baze)
# Paket sadrži hash admin lozinke — ne ide u git, prenosi se samo na poslužitelj (scp) i zatim:
#   bash ~/lovacki/deploy.sh --paket ~/lovacki-podaci.tgz
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p _paket
php _alat/izvoz-mysql.php > _paket/lovacki-baza.sql
tar -czf _paket/lovacki-podaci.tgz \
  --exclude='uploads/.htaccess' --exclude='images/.htaccess' \
  -C web uploads images \
  -C ../_paket lovacki-baza.sql
rm _paket/lovacki-baza.sql
echo "✓ $(du -h _paket/lovacki-podaci.tgz | cut -f1)  _paket/lovacki-podaci.tgz"
