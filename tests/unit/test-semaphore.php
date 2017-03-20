<?php

/**
 * @group semaphore
 */
class Writing_On_GitHub_Semaphore_Test extends Writing_On_GitHub_TestCase {

	public function setUp() {
		parent::setUp();

		$this->semaphore = new Writing_On_GitHub_Semaphore( $this->app );
	}

	public function test_should_default_to_open() {
		$this->assertTrue( $this->semaphore->is_open() );
	}

	public function test_should_unlock() {
		set_transient( Writing_On_GitHub_Semaphore::KEY, Writing_On_GitHub_Semaphore::VALUE_LOCKED );

		$this->semaphore->unlock();

		$this->assertTrue( $this->semaphore->is_open() );
	}

	public function test_should_lock() {
		set_transient( Writing_On_GitHub_Semaphore::KEY, Writing_On_GitHub_Semaphore::VALUE_UNLOCKED );

		$this->semaphore->lock();

		$this->assertFalse( $this->semaphore->is_open() );
	}

	public function test_should_expire() {
		$this->semaphore->lock();

		sleep( MINUTE_IN_SECONDS );
		// A little extra to make sure it's expired.
		sleep( 5 );

		$this->assertTrue( $this->semaphore->is_open() );
	}
}
