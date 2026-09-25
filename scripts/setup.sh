#!/usr/bin/env bash
# Build the clean test instance: WordPress, theme and plugin, the three
# third-party plugins, the scaffold and the demo content. Safe to run again.
#
#   NABU_INSTANCE=clean ./scripts/setup.sh
#
# With the bind address 0.0.0.0 the instance is reachable in the whole
# network with admin/admin — fine for a home network or a tailnet only.
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck disable=SC1091
. scripts/instance.sh

if [ "${NABU_INSTANCE:-staging}" = "staging" ] && [ "${NABU_FORCE:-0}" != "1" ]; then
	echo "setup.sh fills an instance with demo content; staging holds a copy of production." >&2
	echo "Use NABU_INSTANCE=clean ./scripts/setup.sh, or scripts/staging-refresh.sh for staging." >&2
	exit 1
fi

ADMIN_USER="${NABU_ADMIN_USER:-admin}"
ADMIN_PASS="${NABU_ADMIN_PASS:-admin}"
ADMIN_MAIL="${NABU_ADMIN_MAIL:-admin@example.test}"

echo "==> Instance: $COMPOSE_PROJECT_NAME on $URL"
echo "==> Starting containers"
docker compose up -d --wait db wordpress

echo "==> Waiting for WordPress"
for i in $(seq 1 90); do
	code=$(curl -s -o /dev/null -w '%{http_code}' "$URL/" || true)
	if [ "$code" != "000" ]; then
		echo "    reachable (HTTP $code) after ${i}s"
		break
	fi
	sleep 1
	if [ "$i" = "90" ]; then echo "    WordPress does not answer" >&2; exit 1; fi
done

echo "==> Installing WordPress"
if ! wp core is-installed 2>/dev/null; then
	wp core install \
		--url="$URL" \
		--title="NABU Unofficial" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASS" \
		--admin_email="$ADMIN_MAIL" \
		--skip-email
else
	echo "    already installed"
fi

# Formal German: the theme's texts address visitors as "Sie".
echo "==> German locale"
wp language core install de_DE_formal --activate || true
wp site switch-language de_DE_formal || true

echo "==> Theme and plugin"
wp theme activate nabu-unofficial
wp plugin activate nabu-unofficial-content

# Third-party plugins from wordpress.org, optional for the theme but meant to
# be there:
#
# * Nested Pages — the page tree, dragged into order. It writes post_parent
#   and menu_order, which the navigation reads. NABU_PAGE_TREE=0 skips it.
# * Embed Privacy — two-click consent for the map on event pages and for
#   videos in content; without it there is no map, only the link.
#   NABU_EMBEDS=0 skips it.
# * Safe SVG — SVG uploads, for the NABU logo. NABU_SVG=0 skips it.
if [ "${NABU_PAGE_TREE:-1}" = "1" ]; then
	echo "==> Page tree (Nested Pages)"
	wp plugin is-installed wp-nested-pages >/dev/null 2>&1 || wp plugin install wp-nested-pages || true
	wp plugin activate wp-nested-pages || true
fi
if [ "${NABU_EMBEDS:-1}" = "1" ]; then
	echo "==> Third-party content (Embed Privacy)"
	wp plugin is-installed embed-privacy >/dev/null 2>&1 || wp plugin install embed-privacy || true
	wp plugin activate embed-privacy || true
	wp language plugin install embed-privacy de_DE_formal || true
	# Creates the provider list (Google Maps, YouTube …), which otherwise
	# waits for the first back-end request.
	wp eval --user="$ADMIN_USER" 'if ( class_exists( "\\epiphyt\\Embed_Privacy\\Migration" ) ) { \epiphyt\Embed_Privacy\Migration::get_instance()->migrate(); }' || true
fi
if [ "${NABU_SVG:-1}" = "1" ]; then
	echo "==> SVG uploads (Safe SVG)"
	wp plugin is-installed safe-svg >/dev/null 2>&1 || wp plugin install safe-svg || true
	wp plugin activate safe-svg || true
fi

echo "==> Removing unused default themes and plugins"
for t in twentytwentyone twentytwentytwo twentytwentythree twentytwentyfour twentytwentyfive twentytwentysix; do
	wp theme is-installed "$t" >/dev/null 2>&1 && wp theme delete "$t" || true
done
for p in akismet hello; do
	wp plugin is-installed "$p" >/dev/null 2>&1 && wp plugin delete "$p" || true
done

echo "==> Sample data"
if [ -f scripts/sample-data/content.json ]; then
	echo "    already present"
elif [ "${NABU_SAMPLE_DATA:-1}" = "0" ]; then
	echo "    skipped (NABU_SAMPLE_DATA=0) — generated placeholders stay"
elif python3 scripts/fetch-sample-data.py >/dev/null 2>&1; then
	echo "    fetched"
else
	echo "    not reachable — generated placeholders stay"
fi

echo "==> Scaffold and demo content"
wp eval-file /scripts/demo-content.php

# The structure itself is set by the scaffold, from the news page's slug.
echo "==> Permalinks"
wp rewrite flush --hard

echo
echo "Done. $COMPOSE_PROJECT_NAME:"
echo "  $URL"
if [ "$NABU_BIND" = "0.0.0.0" ]; then
	for ip in $(hostname -I 2>/dev/null); do
		case "$ip" in
			172.16.*|172.17.*) continue ;;  # skip Docker bridges
		esac
		echo "  http://$ip:$NABU_PORT"
	done
	echo "  Reachable in the network; credentials are defaults."
fi
echo
echo "  Back end: /wp-admin/  —  $ADMIN_USER / $ADMIN_PASS"
