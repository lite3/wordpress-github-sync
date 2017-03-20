<?php

class Writing_On_GitHub_Response_Test extends Writing_On_GitHub_TestCase {
	const TMP_LOG = '/tmp/debug.log';

	public function setUp() {
		parent::setUp();
		ini_set( 'error_log', self::TMP_LOG );

		$this->response = new Writing_On_GitHub_Response( $this->app );
	}

	public function test_should_log_success() {
		$result = 'Success message.';
		$this->assertTrue( $this->response->success( $result ) );
		$this->assertTrue( file_exists( self::TMP_LOG ) );
		$this->assertContains( 'UTC] ' . $result, file_get_contents( self::TMP_LOG ) );
	}

	public function test_should_log_failure() {
		$result = new WP_Error( 'failure', 'Failure message.');
		$this->assertFalse( $this->response->error( $result ) );
		$this->assertTrue( file_exists( self::TMP_LOG ) );
		$this->assertContains( 'UTC] ' . $result->get_error_message(), file_get_contents( self::TMP_LOG ) );
	}

	public function tearDown() {
		parent::tearDown();

		unlink( self::TMP_LOG );
	}
}
