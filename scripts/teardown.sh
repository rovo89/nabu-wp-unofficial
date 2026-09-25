#!/usr/bin/env bash
# Remove a test instance including database and WordPress core.
# Theme and plugin files are untouched.
#
#   ./scripts/teardown.sh                    # the instance from .env (staging!)
#   NABU_INSTANCE=clean ./scripts/teardown.sh
set -euo pipefail
cd "$(dirname "$0")/.."
# shellcheck disable=SC1091
. scripts/instance.sh

if [ "${NABU_INSTANCE:-staging}" = "staging" ] && [ "${NABU_FORCE:-0}" != "1" ]; then
	echo "This would remove the STAGING instance ($COMPOSE_PROJECT_NAME) with its copy of production." >&2
	echo "Run NABU_FORCE=1 ./scripts/teardown.sh if that is what you want, or NABU_INSTANCE=clean for the clean one." >&2
	exit 1
fi

docker compose down -v
echo "Instance $COMPOSE_PROJECT_NAME removed."
