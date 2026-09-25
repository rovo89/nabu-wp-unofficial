# shellcheck shell=bash
# Sourced by the other scripts: picks the Compose project and port for the
# requested instance. Not executable on its own.
#
#   NABU_INSTANCE=staging (default)  → COMPOSE_PROJECT_NAME / NABU_PORT from .env
#   NABU_INSTANCE=clean              → nabu-clean on NABU_CLEAN_PORT (8091)

if [ -f .env ]; then
	# shellcheck disable=SC1091
	set -a; . ./.env; set +a
fi

case "${NABU_INSTANCE:-staging}" in
	clean)
		export COMPOSE_PROJECT_NAME="${NABU_CLEAN_PROJECT:-nabu-clean}"
		export NABU_PORT="${NABU_CLEAN_PORT:-8091}"
		export NABU_BIND="${NABU_CLEAN_BIND:-0.0.0.0}"
		;;
	staging)
		export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-nabu-staging}"
		export NABU_PORT="${NABU_PORT:-8090}"
		export NABU_BIND="${NABU_BIND:-127.0.0.1}"
		;;
	*)
		echo "NABU_INSTANCE must be 'staging' or 'clean'" >&2
		exit 1
		;;
esac

# shellcheck disable=SC2034 # read by the scripts that source this file
URL="http://localhost:${NABU_PORT}"

# The scripts start db and wordpress first (up --wait); every call gets a
# throwaway wp-cli container without checking them again.
wp() { docker compose run --rm -T --no-deps cli "$@"; }
