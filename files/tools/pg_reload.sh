#!/usr/bin/env bash
# Rebuild the local Postgres dev database from scratch:
#   regenerate schema.pgsql.sql -> drop/create integra_rma -> load schema.
# Local-only guard: refuses to run against anything but localhost.
set -u
PGBIN="${PGBIN:-/c/Users/Rajo/pgsql/bin}"
PGHOST="${PGHOST:-127.0.0.1}"
PGPORT="${PGPORT:-5432}"
PGUSER="${PGUSER:-postgres}"
export PGPASSWORD="${PGPASSWORD:-postgres}"
DB="${DB:-integra_rma}"

case "$PGHOST" in
  127.0.0.1|localhost|::1) ;;
  *) echo "refusing to run against non-local host: $PGHOST"; exit 1 ;;
esac

cd "$(dirname "$0")/.."
python tools/mysql2pg.py >/dev/null || exit 1

"$PGBIN/psql" -U "$PGUSER" -h "$PGHOST" -p "$PGPORT" -tAc \
  "DROP DATABASE IF EXISTS $DB;" >/dev/null 2>&1
"$PGBIN/psql" -U "$PGUSER" -h "$PGHOST" -p "$PGPORT" -tAc \
  "CREATE DATABASE $DB ENCODING 'UTF8';" >/dev/null 2>&1

"$PGBIN/psql" -U "$PGUSER" -h "$PGHOST" -p "$PGPORT" -d "$DB" \
  -v ON_ERROR_STOP=1 -q -f schema.pgsql.sql 2>&1 | head -"${ERRLINES:-15}"

n=$("$PGBIN/psql" -U "$PGUSER" -h "$PGHOST" -p "$PGPORT" -d "$DB" -tAc \
  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")
echo "tables: $n"
