<?php
/**
 * The `data:` protocol must be allowed through kses on the thank you page so the inline QR code renders.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use WP_Mock;
use WP_Mock\Matcher\AnyInstance;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Thank_You
 */
class Thank_You_Unit_Test extends Unit_Testcase {

	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'] );
		parent::tearDown();
	}

	/**
	 * The filter must be attached immediately (not on `init`) because `wp_allowed_protocols()` caches its
	 * result statically the first time it is called.
	 *
	 * @covers ::__construct
	 */
	public function test_kses_allowed_protocols_filter_is_added_in_constructor(): void {
		// WP_Mock's docblock does not include its own AnyInstance matcher in the accepted callback types.
		/* @phpstan-ignore argument.type */
		WP_Mock::expectFilterAdded( 'kses_allowed_protocols', array( new AnyInstance( Thank_You::class ), 'allow_data_protocol_for_inline_qr_code' ) );

		new Thank_You();
	}

	/**
	 * @covers ::allow_data_protocol_for_inline_qr_code
	 * @covers ::is_thank_you_order_confirmation_page
	 */
	public function test_data_protocol_is_added_on_order_received_page(): void {
		$_SERVER['REQUEST_URI'] = '/checkout/order-received/123/?key=wc_order_abc';

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$sut = new Thank_You();

		$result = $sut->allow_data_protocol_for_inline_qr_code( array( 'http', 'https' ) );

		$this->assertSame( array( 'http', 'https', 'data' ), $result );
	}

	/**
	 * @covers ::allow_data_protocol_for_inline_qr_code
	 * @covers ::is_thank_you_order_confirmation_page
	 */
	public function test_data_protocol_is_not_added_on_other_pages(): void {
		$_SERVER['REQUEST_URI'] = '/shop/';

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$sut = new Thank_You();

		$result = $sut->allow_data_protocol_for_inline_qr_code( array( 'http', 'https' ) );

		$this->assertSame( array( 'http', 'https' ), $result );
	}
}
