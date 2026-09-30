<?php
/**
 * Recording the donor's Venmo profile name on a donation.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\API\API_Interface;
use BrianHenryIE\WP_Venmo_Gateway\API\Venmo_Profile;
use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use Mockery;
use Mockery\MockInterface;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Donor_Venmo_Profile
 */
class Donor_Venmo_Profile_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::fetch_donor_venmo_profile
	 * @covers ::__construct
	 */
	public function test_saves_display_name_meta_and_note(): void {
		WP_Mock::userFunction( 'give_get_meta' )->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )->andReturn( 'donor' );
		WP_Mock::userFunction( 'give_update_meta' )->once()->with( 42, Venmo_Gateway::CUSTOMER_VENMO_DISPLAY_NAME_META_KEY, 'Dana Donor' );
		WP_Mock::userFunction( 'give_insert_payment_note' )->once()->with( 42, 'Venmo profile @donor is Dana Donor.' );
		WP_Mock::passthruFunction( '__' );

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );
		$api->shouldReceive( 'get_venmo_profile' )->once()->with( 'donor' )->andReturn( new Venmo_Profile( 'Donor', 'Dana Donor', 'Dana', 'Donor' ) );

		$sut = new Donor_Venmo_Profile( $api, $this->logger );

		$sut->fetch_donor_venmo_profile( 42 );
	}

	/**
	 * @covers ::fetch_donor_venmo_profile
	 */
	public function test_adds_note_when_profile_not_found(): void {
		WP_Mock::userFunction( 'give_get_meta' )->with( 42, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true )->andReturn( 'nobody' );
		WP_Mock::userFunction( 'give_update_meta' )->never();
		WP_Mock::userFunction( 'give_insert_payment_note' )->once()->with( 42, 'Could not find a Venmo profile for @nobody.' );
		WP_Mock::passthruFunction( '__' );

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );
		$api->shouldReceive( 'get_venmo_profile' )->once()->with( 'nobody' )->andReturn( null );

		$sut = new Donor_Venmo_Profile( $api, $this->logger );

		$sut->fetch_donor_venmo_profile( 42 );
	}

	/**
	 * @covers ::fetch_donor_venmo_profile
	 */
	public function test_does_nothing_without_username(): void {
		WP_Mock::userFunction( 'give_get_meta' )->andReturn( '' );
		WP_Mock::userFunction( 'give_update_meta' )->never();
		WP_Mock::userFunction( 'give_insert_payment_note' )->never();

		/** @var API_Interface&MockInterface $api */
		$api = Mockery::mock( API_Interface::class );
		$api->shouldNotReceive( 'get_venmo_profile' );

		$sut = new Donor_Venmo_Profile( $api, $this->logger );

		$sut->fetch_donor_venmo_profile( 42 );
	}
}
