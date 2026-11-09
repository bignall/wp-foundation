<?php
/**
 * Unit test bootstrap.
 *
 * @package Bignall\WPFoundation
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/wordpress/' );
}

require_once __DIR__ . '/Stubs/wp-classes.php';
