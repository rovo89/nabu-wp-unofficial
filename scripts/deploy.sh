#!/usr/bin/env bash
# Deploy the current Git state to production over SSH.
#
#   ./scripts/deploy.sh            # deploys HEAD (working tree must be clean)
#   ./scripts/deploy.sh v1.2.0     # deploys a tag, branch or commit
#   ./scripts/deploy.sh --dry-run  # shows what would happen, changes nothing
#
# Copies, not symlinks: shared hosts commonly confine PHP's open_basedir to
# the document root, so theme and plugin must be real directories below
# wp-content. And no clone on the server, so no .git near the web root: the
# committed state goes across as `git archive` through tar over SSH.
#
# The repository lands beside the document root (scripts, docs, the source of
# the two copies); theme and plugin are swapped into wp-content in one rename
# each, so a half-copied state is never served. Never touched: wp-config.php,
# uploads, the database, and the group data beside the repository.
set -euo pipefail
cd "$(dirname "$0")/.."
if [ -f .env ]; then set -a; . ./.env; set +a; fi

: "${NABU_PROD_SSH:?set NABU_PROD_SSH in .env}"
: "${NABU_PROD_PATH:?set NABU_PROD_PATH in .env}"
repo_dir="${NABU_PROD_REPO:-/nabu-unofficial}"
wp_bin="${NABU_PROD_WP:-wp}"
# A wp-cli outside the PATH brings its directory along: a chrooted host may
# keep the helpers wp-cli calls (mysql, mysqldump) there.
wp_path=""
if [ -n "${NABU_PROD_WP:-}" ]; then
	wp_path="export PATH=\"$(dirname "$NABU_PROD_WP"):\$PATH\""
fi

dry_run=0
ref=""
for arg in "$@"; do
	case "$arg" in
		--dry-run) dry_run=1 ;;
		*) ref="$arg" ;;
	esac
done

if [ -z "$ref" ]; then
	ref="$(git describe --tags --exact-match 2>/dev/null || git rev-parse --abbrev-ref HEAD)"
fi

if [ -n "$(git status --porcelain)" ]; then
	echo "Working tree is not clean — commit or stash first." >&2
	exit 1
fi

commit="$(git rev-parse --short "$ref")"
subject="$(git log -1 --format=%s "$ref")"

echo "==> $ref ($commit) -> $NABU_PROD_SSH:$repo_dir"
echo "    $subject"

if [ "$dry_run" = 1 ]; then
	echo "    --dry-run: nothing transferred."
	git archive --format=tar "$ref" | tar -t | sed 's/^/    /' | head -20
	exit 0
fi

# The tarball takes stdin, so the script goes across as the SSH command, with
# the values filled in here.
remote_script=$(cat <<REMOTE
set -euo pipefail
$wp_path

repo='$repo_dir'
docroot='$NABU_PROD_PATH'
wp='$wp_bin'

# 1. Unpack the repository beside the document root.
rm -rf "\$repo.new" "\$repo.old"
mkdir -p "\$repo.new"
tar -x --strip-components=1 -C "\$repo.new"
[ -d "\$repo" ] && mv "\$repo" "\$repo.old"
mv "\$repo.new" "\$repo"
rm -rf "\$repo.old"
echo "    repository unpacked"

# 2. Swap theme and plugin into wp-content.
swap() {
	src="\$1"; dest="\$2"
	rm -rf "\$dest.new" "\$dest.old"
	cp -a "\$src" "\$dest.new"
	[ -d "\$dest" ] && mv "\$dest" "\$dest.old"
	mv "\$dest.new" "\$dest"
	rm -rf "\$dest.old"
	echo "    \$(basename "\$dest") updated"
}
swap "\$repo/themes/nabu-unofficial"              "\$docroot/wp-content/themes/nabu-unofficial"
swap "\$repo/plugins/nabu-unofficial-content"     "\$docroot/wp-content/plugins/nabu-unofficial-content"

# 3. Make sure both are active, then clear caches.
cd "\$docroot"
\$wp theme is-active nabu-unofficial >/dev/null 2>&1 || \$wp theme activate nabu-unofficial
\$wp plugin is-active nabu-unofficial-content >/dev/null 2>&1 || \$wp plugin activate nabu-unofficial-content
\$wp rewrite flush --hard --quiet
\$wp cache flush --quiet 2>/dev/null || true

printf '%s  %s  %s\n' "\$(date -u +%FT%TZ)" '$commit' '$ref' >> '${repo_dir%/*}/nabu-deployed.log'
echo "    theme \$(\$wp theme get nabu-unofficial --field=version), plugin \$(\$wp plugin get nabu-unofficial-content --field=version)"
REMOTE
)

git archive --format=tar --prefix=nabu-deploy/ "$ref" | ssh "$NABU_PROD_SSH" "$remote_script"

echo "Done."
