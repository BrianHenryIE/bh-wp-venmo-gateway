<?php
/**
 * Tests for the Venmo_Username helper.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Venmo_Username
 */
class Venmo_Username_Test extends Unit_Testcase {

	/**
	 * Saved values are stored as the bare handle.
	 *
	 * @dataProvider provide_sanitize
	 * @covers ::sanitize
	 *
	 * @param string $input    The value entered by the user.
	 * @param string $expected The value that should be stored.
	 */
	public function test_sanitize( string $input, string $expected ): void {
		$this->assertSame( $expected, Venmo_Username::sanitize( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function provide_sanitize(): array {
		return array(
			'no @'           => array( 'testvendor', 'testvendor' ),
			'leading @'      => array( '@testvendor', 'testvendor' ),
			'repeated @'     => array( '@@testvendor', 'testvendor' ),
			'surrounding ws' => array( '  @testvendor  ', 'testvendor' ),
			'inner @ kept'   => array( '@test@vendor', 'test@vendor' ),
			'empty'          => array( '', '' ),
			'only @'         => array( '@', '' ),
		);
	}

	/**
	 * Displayed values always have exactly one leading "@" (or are empty).
	 *
	 * @dataProvider provide_for_display
	 * @covers ::for_display
	 *
	 * @param string $input    The stored (or raw) value.
	 * @param string $expected The value that should be displayed.
	 */
	public function test_for_display( string $input, string $expected ): void {
		$this->assertSame( $expected, Venmo_Username::for_display( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function provide_for_display(): array {
		return array(
			'bare stored' => array( 'testvendor', '@testvendor' ),
			'already @'   => array( '@testvendor', '@testvendor' ),
			'repeated @'  => array( '@@testvendor', '@testvendor' ),
			'empty stays' => array( '', '' ),
			'only @'      => array( '@', '' ),
		);
	}
}
