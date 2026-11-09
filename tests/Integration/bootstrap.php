<?php
/**
 * Integration test bootstrap. Requires the WordPress test library; see bin/install-wp-tests.sh.
 *
 * @package Bignall\WPFoundation
 */

$root     = dirname( __DIR__, 2 );
$testsDir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $testsDir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library in {$testsDir}. Run bin/install-wp-tests.sh first." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $root . '/vendor/autoload.php';
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $root . '/vendor/yoast/phpunit-polyfills' );

require_once $testsDir . '/includes/functions.php';
require_once $testsDir . '/includes/bootstrap.php';
