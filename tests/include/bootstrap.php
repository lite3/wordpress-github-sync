<?php
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

define( 'WRITING_ON_GITHUB_TEST', true );

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../../writing-on-github.php';
	remove_action( 'plugins_loaded', array( Writing_On_GitHub::$instance, 'boot' ) );
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

require dirname( __FILE__ ) . '/../../vendor/jdgrimes/wp-http-testcase/wp-http-testcase.php';
require dirname( __FILE__ ) . '/../../tests/include/testcase.php';

error_reporting( E_ALL ^ E_DEPRECATED );
