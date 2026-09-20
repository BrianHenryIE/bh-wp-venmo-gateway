<?php
/**
 * Unit tests for the development plugin's Venmo payment email builder: the MIME/quoted-printable
 * substitution of the order's details into the template email.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\API;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use WC_Order;

require_once codecept_root_dir( 'development-plugin/api/class-created-email.php' );
require_once codecept_root_dir( 'development-plugin/api/class-venmo-payment-email.php' );

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\API\Venmo_Payment_Email
 */
class Venmo_Payment_Email_Unit_Test extends Unit_Testcase {

	protected function setup(): void {
		parent::setup();

		// The template path is built from these, as it would be in the running plugin.
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', dirname( codecept_root_dir() ) );
		}
		if ( ! defined( 'BH_WP_VENMO_GATEWAY_BASENAME' ) ) {
			define( 'BH_WP_VENMO_GATEWAY_BASENAME', basename( codecept_root_dir() ) . '/bh-wp-venmo-gateway.php' );
		}

		\WP_Mock::userFunction( 'site_url' )->andReturn( 'https://example.org' );
		\WP_Mock::userFunction( 'wp_parse_url' )->andReturnUsing( fn( string $url, int $component ) => parse_url( $url, $component ) );
	}

	/**
	 * An order stub with the details the builder reads.
	 *
	 * @param string $first_name Billing first name.
	 * @param string $last_name  Billing last name.
	 * @param string $total      Order total as WooCommerce returns it.
	 * @param int    $id         Order id.
	 */
	private function make_order( string $first_name, string $last_name, string $total, int $id ): WC_Order {
		return $this->makeEmpty(
			WC_Order::class,
			array(
				'get_billing_first_name' => $first_name,
				'get_billing_last_name'  => $last_name,
				'get_total'              => $total,
				'get_id'                 => $id,
			)
		);
	}

	/**
	 * The raw MIME's headers and decoded HTML part.
	 *
	 * @param string $raw The built MIME message.
	 *
	 * @return array{0:string, 1:string}
	 */
	private function split_mime( string $raw ): array {
		list( $headers, $body ) = explode( "\n\n", $raw, 2 );
		$this->assertSame( 1, preg_match( '/boundary="([^"]+)"/', $headers, $matches ) );
		$boundary = $matches[1] ?? '';
		$this->assertNotSame( '', $boundary );
		foreach ( explode( '--' . $boundary, $body ) as $part ) {
			if ( false !== strpos( $part, 'Content-Type: text/html' ) ) {
				list( , $part_body ) = explode( "\n\n", $part, 2 );
				return array( $headers, quoted_printable_decode( $part_body ) );
			}
		}
		$this->fail( 'No HTML part in the built email.' );
	}

	/**
	 * @covers ::build_mime_for_order
	 * @covers ::substitute_html
	 */
	public function test_substitutes_order_details_into_template(): void {
		$order = $this->make_order( 'Zed', 'Tester', '123.45', 77 );

		list( $headers, $html ) = $this->split_mime( ( new Venmo_Payment_Email() )->build_mime_for_order( $order ) );

		$this->assertStringContainsString( "\nSubject: Zed Tester paid you $123.45\n", $headers . "\n" );
		$this->assertMatchesRegularExpression( '/^Message-ID: <development-plugin-77-\w+@example\.org>$/m', $headers );

		$this->assertStringContainsString( 'Zed Tester paid you $123.45', $html );
		$this->assertStringContainsString( 'line-height:40px">123</div>', $html );
		$this->assertStringContainsString( 'padding-top:1px">45</div>', $html );
		$this->assertStringContainsString( 'Order #77', $html );

		$this->assertStringNotContainsString( Venmo_Payment_Email::TEMPLATE_CUSTOMER_NAME, $html );
		$this->assertStringNotContainsString( Venmo_Payment_Email::TEMPLATE_AMOUNT, $html );
		$this->assertStringNotContainsString( Venmo_Payment_Email::TEMPLATE_NOTE, $html );
		$this->assertStringNotContainsString( Venmo_Payment_Email::TEMPLATE_TRANSACTION_ID, $html );
	}

	/**
	 * @covers ::build_mime_for_order
	 */
	public function test_amount_is_formatted_to_two_decimals_and_name_falls_back_to_template(): void {
		$order = $this->make_order( '', '', '18', 5 );

		list( $headers, $html ) = $this->split_mime( ( new Venmo_Payment_Email() )->build_mime_for_order( $order ) );

		$this->assertStringContainsString( 'Subject: John Doe paid you $18.00', $headers );
		$this->assertStringContainsString( 'line-height:40px">18</div>', $html );
		$this->assertStringContainsString( 'padding-top:1px">00</div>', $html );
	}

	/**
	 * Each email must have a unique Message-ID, or the ingress endpoint treats it as a duplicate.
	 *
	 * @covers ::build_mime_for_order
	 */
	public function test_message_id_is_unique_per_build(): void {
		$order = $this->make_order( 'Zed', 'Tester', '1.00', 9 );
		$sut   = new Venmo_Payment_Email();

		$this->assertSame( 1, preg_match( '/^Message-ID: (.*)$/m', $sut->build_mime_for_order( $order ), $first ) );
		$this->assertSame( 1, preg_match( '/^Message-ID: (.*)$/m', $sut->build_mime_for_order( $order ), $second ) );
		$this->assertNotSame( '', $first[1] ?? '' );
		$this->assertNotSame( $first[1] ?? '', $second[1] ?? '' );
	}
}
