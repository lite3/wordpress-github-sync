<?php

/**
 * @group api
 */
class Writing_On_GitHub_Api_Test extends Writing_On_GitHub_TestCase {
	public function setUp() {
		parent::setUp();

		$this->api = new Writing_On_GitHub_Api( $this->app );
	}

	public function test_should_load_fetch() {
		$this->assertInstanceOf( 'Writing_On_GitHub_Fetch_Client', $this->api->fetch() );
	}

	public function test_should_load_persist() {
		$this->assertInstanceOf( 'Writing_On_GitHub_Persist_Client', $this->api->persist() );
	}
}

