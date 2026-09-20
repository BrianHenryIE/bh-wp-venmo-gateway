<?php
/**
 * Unit tests for Pattern_3's regexes against the real Venmo email fixture.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\API\Pattern_3
 */
class Pattern_3_Unit_Test extends Unit_Testcase {

	/**
	 * The fixture's decoded HTML body with whitespace collapsed, as the reconcile library's Email_Parser prepares it.
	 *
	 * @see \BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\API\Email_Parser::parse_email_with_pattern_set()
	 */
	private function get_fixture_html(): string {
		$raw = file_get_contents( codecept_data_dir( 'John Doe paid you $46.00.eml' ) );
		$this->assertNotFalse( $raw );

		$raw = str_replace( "\r\n", "\n", $raw );

		$this->assertSame( 1, preg_match( '/boundary="([^"]+)"/', $raw, $matches ) );
		$boundary = $matches[1] ?? '';
		$this->assertNotSame( '', $boundary );
		foreach ( explode( '--' . $boundary, $raw ) as $part ) {
			if ( false === strpos( $part, 'Content-Type: text/html' ) ) {
				continue;
			}
			list( , $body ) = explode( "\n\n", $part, 2 );
			return (string) preg_replace( '/\s+/', ' ', quoted_printable_decode( $body ) );
		}
		$this->fail( 'No HTML part in the fixture email.' );
	}

	/**
	 * @param string $regex The pattern's regex.
	 */
	private function match( string $regex ): string {
		$this->assertSame( 1, preg_match( $regex, $this->get_fixture_html(), $matches ), "Regex did not match: {$regex}" );
		return trim( $matches[1] ?? '' );
	}

	/**
	 * @covers ::get_customer_name_regex
	 */
	public function test_customer_name(): void {
		$this->assertSame( 'John Doe', $this->match( (string) ( new Pattern_3() )->get_customer_name_regex() ) );
	}

	/**
	 * @covers ::get_amount_regex
	 */
	public function test_amount(): void {
		$this->assertSame( '46.00', $this->match( ( new Pattern_3() )->get_amount_regex() ) );
	}

	/**
	 * @covers ::get_notes_array_regex
	 */
	public function test_note(): void {
		$this->assertSame( 'None of your damn biz', $this->match( ( new Pattern_3() )->get_notes_array_regex()['note'] ) );
	}

	/**
	 * @covers ::get_transaction_id_regex
	 */
	public function test_transaction_id(): void {
		$this->assertSame( '4673461554100496023', $this->match( (string) ( new Pattern_3() )->get_transaction_id_regex() ) );
	}

	/**
	 * @covers ::get_transaction_url_regex
	 */
	public function test_transaction_url(): void {
		$this->assertStringStartsWith( 'https://venmo.com/story/4673461555274381813', $this->match( (string) ( new Pattern_3() )->get_transaction_url_regex() ) );
	}

	/**
	 * The current Venmo email has neither the customer's email address nor their Venmo username.
	 *
	 * @covers ::get_customer_email_regex
	 * @covers ::get_customer_id_regex
	 */
	public function test_customer_email_and_id_are_not_extracted(): void {
		$this->assertNull( ( new Pattern_3() )->get_customer_email_regex() );
		$this->assertNull( ( new Pattern_3() )->get_customer_id_regex() );
	}
}
