#!/usr/bin/env bash
#
# Run the PHPUnit suite inside wp-env.
#
# Requires Docker to be running. First run takes a few minutes while wp-env
# pulls the WordPress, MySQL and PHPUnit images.
#
#   bin/test.sh              run the suite
#   bin/test.sh --multisite  run it against a network install
#   bin/test.sh --filter X   pass extra args straight to phpunit
set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if ! docker info >/dev/null 2>&1; then
	echo "Docker is not running. Start Docker Desktop and try again." >&2
	exit 1
fi

MULTISITE=0
ARGS=()
for arg in "$@"; do
	if [ "$arg" = "--multisite" ]; then
		MULTISITE=1
	else
		ARGS+=( "$arg" )
	fi
done

# Bring the environment up (idempotent).
npx --yes @wordpress/env start

# Dev dependencies live inside the container, so nothing lands in the repo.
npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/wp-stats \
	composer install --no-interaction --no-progress

npx --yes @wordpress/env run tests-cli --env-cwd=wp-content/plugins/wp-stats \
	env WP_MULTISITE="$MULTISITE" vendor/bin/phpunit ${ARGS[@]+"${ARGS[@]}"}
