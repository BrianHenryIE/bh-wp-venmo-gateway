<?php
/**
 * Fetching and parsing a public Venmo profile page.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use WP_Error;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\API\Venmo_Profile_Fetcher
 */
class Venmo_Profile_Fetcher_Unit_Test extends Unit_Testcase {

	protected function get_fixture_html(): string {
		$html = file_get_contents( codecept_data_dir( 'venmo-profile-page.html' ) );
		$this->assertIsString( $html );
		return $html;
	}

	/**
	 * @covers ::parse_profile_page
	 */
	public function test_parses_profile_from_next_data(): void {
		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$profile = $sut->parse_profile_page( $this->get_fixture_html() );

		$this->assertInstanceOf( Venmo_Profile::class, $profile );
		$this->assertSame( 'Brian Henry', $profile->display_name );
		$this->assertSame( 'BrianHenryIE', $profile->username );
		$this->assertSame( 'Brian', $profile->first_name );
		$this->assertSame( 'Henry', $profile->last_name );
	}

	/**
	 * @return array<string, array{html:string}>
	 */
	public function unparseable_pages_provider(): array {
		return array(
			'no next data'   => array( 'html' => '<html><body>Venmo</body></html>' ),
			'invalid json'   => array( 'html' => '<script id="__NEXT_DATA__" type="application/json">{not json</script>' ),
			'no user'        => array( 'html' => '<script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"pageType":"error"}}}</script>' ),
			'no displayName' => array( 'html' => '<script id="__NEXT_DATA__" type="application/json">{"props":{"pageProps":{"user":{"username":"x"}}}}</script>' ),
		);
	}

	/**
	 * @covers ::parse_profile_page
	 *
	 * @dataProvider unparseable_pages_provider
	 *
	 * @param string $html A page without a usable profile.
	 */
	public function test_returns_null_when_page_has_no_profile( string $html ): void {
		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$this->assertNull( $sut->parse_profile_page( $html ) );
	}

	/**
	 * @covers ::get_profile_url
	 */
	public function test_profile_url_strips_leading_at(): void {
		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$this->assertSame( 'https://venmo.com/u/brianhenryie', $sut->get_profile_url( '@brianhenryie' ) );
	}

	/**
	 * @covers ::fetch
	 * @covers ::__construct
	 */
	public function test_fetch_returns_profile_on_200(): void {
		$response = array( 'body' => $this->get_fixture_html() );

		WP_Mock::userFunction( 'wp_safe_remote_get' )
			->once()
			->with( 'https://venmo.com/u/brianhenryie', WP_Mock\Functions::type( 'array' ) )
			->andReturn( $response );
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( $response['body'] );

		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$profile = $sut->fetch( 'brianhenryie' );

		$this->assertNotNull( $profile );
		$this->assertSame( 'Brian Henry', $profile->display_name );
	}

	/**
	 * @covers ::fetch
	 */
	public function test_fetch_returns_null_on_404(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )->once()->andReturn( array() );
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 404 );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->never();

		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$this->assertNull( $sut->fetch( 'nobody' ) );
	}

	/**
	 * @covers ::fetch
	 */
	public function test_fetch_returns_null_on_wp_error(): void {
		$error = \Mockery::mock( WP_Error::class );
		$error->shouldReceive( 'get_error_message' )->andReturn( 'cURL error 28' );
		$error->shouldReceive( 'get_error_data' )->andReturn( null );

		WP_Mock::userFunction( 'wp_safe_remote_get' )->once()->andReturn( $error );
		WP_Mock::userFunction( 'is_wp_error' )->andReturn( true );
		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->never();

		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$this->assertNull( $sut->fetch( 'brianhenryie' ) );
	}

	/**
	 * @covers ::fetch
	 */
	public function test_fetch_does_not_request_for_empty_username(): void {
		WP_Mock::userFunction( 'wp_safe_remote_get' )->never();

		$sut = new Venmo_Profile_Fetcher( $this->logger );

		$this->assertNull( $sut->fetch( '@' ) );
	}
}
