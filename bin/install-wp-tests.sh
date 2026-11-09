#!/usr/bin/env bash
# Install the WordPress test library and create the test database.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#   wp-version: latest (default), trunk, or a release such as 6.4 or 6.7.2
#
# Installs wordpress-develop into $WP_DEVELOP_DIR (default: $TMPDIR/wordpress-develop).
# Point the tests at it with: export WP_TESTS_DIR="$WP_DEVELOP_DIR/tests/phpunit"
set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_DEVELOP_DIR=${WP_DEVELOP_DIR-$TMPDIR/wordpress-develop}
REPO=https://github.com/WordPress/wordpress-develop.git

resolve_ref() {
	if [ "$WP_VERSION" = "trunk" ]; then
		echo trunk
		return
	fi
	local tags
	tags=$(git ls-remote --tags --refs "$REPO" | sed 's#.*refs/tags/##' | grep -E '^[0-9]+\.[0-9]+(\.[0-9]+)?$' | sort -V)
	if [ "$WP_VERSION" = "latest" ]; then
		echo "$tags" | tail -n 1
	else
		# "6.4" resolves to the newest 6.4.x release; "6.4.3" to itself.
		echo "$tags" | grep -E "^${WP_VERSION//./\\.}(\.[0-9]+)?$" | tail -n 1
	fi
}

install_wp() {
	local ref
	ref=$(resolve_ref)
	if [ -z "$ref" ]; then
		echo "Unknown WordPress version: $WP_VERSION"
		exit 1
	fi
	echo "Installing WordPress $ref into $WP_DEVELOP_DIR"

	rm -rf "$WP_DEVELOP_DIR"
	git clone --quiet --depth 1 --branch "$ref" "$REPO" "$WP_DEVELOP_DIR"

	local config="$WP_DEVELOP_DIR/wp-tests-config.php"
	sed \
		-e "s:dirname( __FILE__ ) . '/src/':'$WP_DEVELOP_DIR/src/':" \
		-e "s:__DIR__ . '/src/':'$WP_DEVELOP_DIR/src/':" \
		-e "s/youremptytestdbnamehere/$DB_NAME/" \
		-e "s/yourusernamehere/$DB_USER/" \
		-e "s/yourpasswordhere/$DB_PASS/" \
		-e "s|localhost|${DB_HOST}|" \
		"$WP_DEVELOP_DIR/wp-tests-config-sample.php" > "$config"
}

create_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return
	fi

	local host=${DB_HOST%%:*} port="" extra=""
	if [[ "$DB_HOST" == *:* ]]; then
		port=${DB_HOST#*:}
		if [[ "$port" =~ ^[0-9]+$ ]]; then
			extra="--port=$port --protocol=tcp"
		else
			extra="--socket=$port"
		fi
	elif [ "$host" != "localhost" ]; then
		extra="--protocol=tcp"
	fi

	# shellcheck disable=SC2086
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$host" $extra 2>/dev/null \
		|| echo "Database $DB_NAME already exists or could not be created; continuing."
}

install_wp
create_db
echo "Done. export WP_TESTS_DIR=$WP_DEVELOP_DIR/tests/phpunit"
