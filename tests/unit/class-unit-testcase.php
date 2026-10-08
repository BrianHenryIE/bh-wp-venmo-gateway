<?php

namespace BrianHenryIE\WP_Venmo_Gateway;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerInterface;
use Codeception\Test\Unit;
use WP_Mock;
use function Patchwork\restoreAll;

class Unit_Testcase extends Unit {

	protected LoggerInterface $logger;

	protected function setup(): void {
		WP_Mock::setUp();

		WP_Mock::userFunction( 'wp_unslash' )->andReturnArg( 0 );
		WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );

		// Use the Strauss-prefixed logger interface for this project.
		$this->logger = new class() extends ColorLogger implements LoggerInterface {
		};
	}

	protected function tearDown(): void {
		parent::_tearDown();
		WP_Mock::tearDown();
		restoreAll();
	}
}
