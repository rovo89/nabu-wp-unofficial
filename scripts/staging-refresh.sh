#!/usr/bin/env bash
# Replace the local staging instance with a copy of production.
#
#   ./scripts/staging-refresh.sh
#
# Needs NABU_PROD_SSH, NABU_PROD_PATH and NABU_PROD_URL in .env and wp-cli on
# the server (NABU_PROD_WP if it is not on the PATH there). Reads from
# production only. Locally it replaces database and uploads, rewrites the site
# address, and turns off indexing and outgoing mail.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck disable=SC1091
. scripts/instance.sh

: "${NABU_PROD_SSH:?set NABU_PROD_SSH in .env}"
: "${NABU_PROD_PATH:?set NABU_PROD_PATH in .env}"
: "${NABU_PROD_URL:?set NABU_PROD_URL in .env}"

if [ "${NABU_INSTANCE:-staging}" != "staging" ]; then
	echo "Only the staging instance holds a copy of production." >&2
	exit 1
fi

# wp-cli on the server. One outside the PATH brings its directory along: a
# chrooted host may keep the helpers wp-cli calls (mysql, mysqldump) there.
prod_wp="${NABU_PROD_WP:-wp}"
prod_path=""
if [ -n "${NABU_PROD_WP:-}" ]; then
	prod_path="PATH='$(dirname "$NABU_PROD_WP")':\$PATH "
fi
prod() { ssh "$NABU_PROD_SSH" "cd '$NABU_PROD_PATH' && $prod_path'$prod_wp' $*"; }

echo "==> Instance: $COMPOSE_PROJECT_NAME on $URL"
docker compose up -d --wait db wordpress
for _ in $(seq 1 90); do
	[ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" || true)" != "000" ] && break
	sleep 1
done

# A private directory; the dump inside must be readable for wp-cli, which
# runs as www-data in its container.
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
dump="$work/prod.sql"
uploads="$work/uploads"

echo "==> Database from $NABU_PROD_SSH"
prod db export - --single-transaction --quick > "$dump"
chmod 644 "$dump"
prod_prefix=$(prod db prefix)

echo "==> Importing"
wp db reset --yes --quiet
docker compose run --rm -T --no-deps -v "$dump:/tmp/prod.sql:ro" cli db import /tmp/prod.sql

# Production's table prefix is its own. The image's wp-config.php takes the
# local one from the environment.
local_prefix=$(docker compose exec -T wordpress sh -c 'printf %s "${WORDPRESS_TABLE_PREFIX:-wp_}"')
if [ "$prod_prefix" != "$local_prefix" ]; then
	for table in $(wp db query "SHOW TABLES" --skip-column-names); do
		case "$table" in
			"$prod_prefix"*) wp db query "RENAME TABLE \`$table\` TO \`$local_prefix${table#"$prod_prefix"}\`" ;;
		esac
	done
	# Roles and capabilities are stored under prefixed keys.
	wp db query "UPDATE ${local_prefix}options SET option_name = '${local_prefix}user_roles' WHERE option_name = '${prod_prefix}user_roles'"
	wp db query "UPDATE ${local_prefix}usermeta SET meta_key = CONCAT('${local_prefix}', SUBSTRING(meta_key, $((${#prod_prefix} + 1)))) WHERE LEFT(meta_key, ${#prod_prefix}) = BINARY '${prod_prefix}'"
fi

# The full address, then protocol-relative ones — not the bare domain, which
# would also rewrite e-mail addresses.
wp search-replace "$NABU_PROD_URL" "$URL" --all-tables --quiet
wp search-replace "//${NABU_PROD_URL#*//}" "//${URL#*//}" --all-tables --quiet
wp cache flush --quiet || true

# Before anything else can go wrong: never indexed, never sending mail.
echo "==> Local safety"
wp option update blog_public 0 --quiet
wp plugin is-installed disable-emails >/dev/null 2>&1 || wp plugin install disable-emails --quiet
wp plugin activate disable-emails --quiet
wp user update "$(wp user list --role=administrator --field=ID --number=1)" --user_pass="${NABU_ADMIN_PASS:-admin}" --skip-email --quiet

echo "==> Uploads"
mkdir "$uploads"
ssh "$NABU_PROD_SSH" "tar -cz -C '$NABU_PROD_PATH/wp-content/uploads' ." | tar -xz -C "$uploads"
container=$(docker compose ps -q wordpress)
docker exec "$container" rm -rf /var/www/html/wp-content/uploads
docker cp "$uploads/." "$container:/var/www/html/wp-content/uploads/"
docker exec "$container" chown -R www-data:www-data /var/www/html/wp-content/uploads

# Translations and third-party plugins are files, not database: fetch what
# the copied site expects and this instance lacks.
echo "==> Language and plugins of production"
language=$(wp option get WPLANG)
if [ -n "$language" ]; then
	wp language core install "$language" --quiet
	wp language plugin install --all "$language" --quiet || true
fi
for plugin in $(prod plugin list --status=active --field=name); do
	[ "$plugin" = nabu-unofficial-content ] && continue
	if wp plugin is-installed "$plugin" || wp plugin install "$plugin" --quiet; then
		wp plugin activate "$plugin" --quiet
	else
		echo "    $plugin is not on wordpress.org — copy it by hand." >&2
	fi
done

echo "Done. Staging is a copy of production as of now: $URL"
