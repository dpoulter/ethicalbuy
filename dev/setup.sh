#!/usr/bin/env bash
#
# Creates a local development database for Ethical Buy and loads sample data.
#
# Usage:
#   ./dev/setup.sh                 # create db + user, load schema, seed, migrations
#   DB_PASSWORD=secret ./dev/setup.sh
#
# Requires a running MySQL or MariaDB that you can reach as an admin user.
# Set ADMIN_USER / ADMIN_PASSWORD if root without a password doesn't work.
#
# This drops and recreates the brand tables. Never point it at production.

set -euo pipefail

DB_NAME="${DB_NAME:-ethicalbuy}"
DB_USER="${DB_USER:-ethicalbuy}"
DB_PASSWORD="${DB_PASSWORD:-ethicalbuy_dev}"
ADMIN_USER="${ADMIN_USER:-root}"

# The importer runs as its own account: it needs to create categories, which
# the web application must never be able to do.
IMPORT_USER="${IMPORT_USER:-ethicalbuy_import}"
IMPORT_PASSWORD="${IMPORT_PASSWORD:-ethicalbuy_import_dev}"

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$HERE")"

# --default-character-set is required: without it the client may default to
# latin1 and silently double-encode every non-ASCII byte in the .sql files.
admin() {
    if [ -n "${ADMIN_PASSWORD:-}" ]; then
        mysql --default-character-set=utf8mb4 -u "$ADMIN_USER" -p"$ADMIN_PASSWORD" "$@"
    else
        mysql --default-character-set=utf8mb4 -u "$ADMIN_USER" "$@"
    fi
}

echo "==> Creating database '$DB_NAME' and user '$DB_USER'"
admin <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
CREATE USER IF NOT EXISTS '$IMPORT_USER'@'localhost' IDENTIFIED BY '$IMPORT_PASSWORD';
SQL

echo "==> Loading schema"
admin "$DB_NAME" < "$HERE/schema.sql"

echo "==> Loading log tables"
admin "$DB_NAME" <<'SQL'
CREATE TABLE IF NOT EXISTS message_log (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    module       VARCHAR(100),
    message_text TEXT,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    job_name   VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL

echo "==> Applying migrations"
for f in "$ROOT"/migrations/*.sql; do
    [ -e "$f" ] || continue
    echo "    $(basename "$f")"
    admin "$DB_NAME" < "$f"
done

echo "==> Seeding sample data (invented brands -- never ship this)"
admin "$DB_NAME" < "$HERE/seed.sql"

# Granted last: the tables have to exist before they can be granted on.
#
# The public site only reads brand_v and appends to the log tables. The admin
# pages additionally read categories/owners and write to brands. Everything
# else in the schema stays out of reach.
echo "==> Granting least privilege to '$DB_USER'"
admin <<SQL
GRANT SELECT ON \`$DB_NAME\`.brand_v          TO '$DB_USER'@'localhost';
GRANT INSERT ON \`$DB_NAME\`.contact_messages TO '$DB_USER'@'localhost';
GRANT INSERT ON \`$DB_NAME\`.message_log      TO '$DB_USER'@'localhost';
GRANT INSERT ON \`$DB_NAME\`.jobs             TO '$DB_USER'@'localhost';

-- admin CRUD
GRANT SELECT                         ON \`$DB_NAME\`.categories TO '$DB_USER'@'localhost';
GRANT SELECT                         ON \`$DB_NAME\`.owners     TO '$DB_USER'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`$DB_NAME\`.brands     TO '$DB_USER'@'localhost';

-- importer: may add brands and categories, may never delete anything
GRANT SELECT, INSERT, UPDATE ON \`$DB_NAME\`.brands     TO '$IMPORT_USER'@'localhost';
GRANT SELECT, INSERT         ON \`$DB_NAME\`.categories TO '$IMPORT_USER'@'localhost';
GRANT SELECT, INSERT         ON \`$DB_NAME\`.owners     TO '$IMPORT_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

echo
echo "Done. Run the site with:"
echo
echo "  DB_NAME=$DB_NAME DB_USER=$DB_USER DB_PASSWORD=$DB_PASSWORD APP_DEBUG=1 \\"
echo "    php -S 127.0.0.1:8000 -t public"
echo
echo "Import brand facts (dry run; add --apply to write):"
echo
echo "  DB_NAME=$DB_NAME DB_USER=$IMPORT_USER DB_PASSWORD=$IMPORT_PASSWORD \\"
echo "    php bin/import-openfoodfacts.php --category=en:chocolates --contact=you@example.com"
echo
admin "$DB_NAME" -e "SELECT COUNT(*) AS brands, COUNT(rating) AS rated FROM brand_v;"
