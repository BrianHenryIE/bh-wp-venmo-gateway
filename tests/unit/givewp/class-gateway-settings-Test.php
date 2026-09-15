<?php
/**
 * Unit tests for the log level field on the GiveWP gateway settings page, which is
 * shared with the WooCommerce gateway via the `bh_wp_venmo_gateway_log_level` option.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Gateway_Settings
 */
class Gateway_Settings_Test extends Unit_Testcase {

	/**
	 * The GiveWP settings section the mocked `give_get_current_setting_section()` reports.
	 */
	private string $current_section = 'venmo';

	protected function setup(): void {
		parent::setup();

		\WP_Mock::userFunction( '__' )->andReturnArg( 0 );
		\WP_Mock::userFunction( 'esc_html__' )->andReturnArg( 0 );
		\WP_Mock::passthruFunction( 'esc_html' );
		\WP_Mock::passthruFunction( 'esc_url' );
		\WP_Mock::userFunction( 'admin_url' )->andReturnUsing( fn( string $path ) => 'https://example.org/wp-admin/' . $path );
		\WP_Mock::userFunction( 'give_get_current_setting_section' )->andReturnUsing( fn() => $this->current_section );
		\WP_Mock::userFunction( 'give_get_payments' )->andReturn( array() );
	}

	/**
	 * Mock `get_option()` so the shared log level option returns the given value.
	 *
	 * @param string $log_level The value `bh_wp_venmo_gateway_log_level` should return.
	 */
	private function mock_shared_log_level( string $log_level ): void {
		\WP_Mock::userFunction( 'get_option' )->andReturnUsing(
			function ( string $option, $default_value = false ) use ( $log_level ) {
				return 'bh_wp_venmo_gateway_log_level' === $option ? $log_level : $default_value;
			}
		);
	}

	/**
	 * Find the log level field definition in the list GiveWP will render.
	 *
	 * @param array<int, array<string, mixed>> $settings The flat list of GiveWP field definitions.
	 * @return array<string, mixed>|null
	 */
	private function find_log_level_field( array $settings ): ?array {
		foreach ( $settings as $field ) {
			if ( isset( $field['id'] ) && Gateway_Settings::LOG_LEVEL_FIELD_ID === $field['id'] ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * @covers ::register_settings
	 */
	public function test_register_settings_adds_log_level_select_with_shared_value_and_logs_link(): void {
		$this->mock_shared_log_level( 'debug' );
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_log_level' )->andReturn( 'debug' );
		\WP_Mock::userFunction( 'give_update_option' )->never();

		$settings = ( new Gateway_Settings() )->register_settings( array() );

		$field = $this->find_log_level_field( $settings );

		$this->assertNotNull( $field );
		$this->assertSame( 'select', $field['type'] );
		$this->assertSame( 'debug', $field['default'] );
		$this->assertSame( array( 'none', 'error', 'warning', 'notice', 'info', 'debug' ), array_keys( $field['options'] ) );
		$this->assertStringContainsString( 'admin.php?page=bh-wp-venmo-gateway-logs', $field['desc'] );
		$this->assertStringContainsString( 'View Logs', $field['desc'] );
	}

	/**
	 * A change made on the WooCommerce settings page should be reflected in GiveWP's stored value.
	 *
	 * @covers ::register_settings
	 */
	public function test_register_settings_syncs_stale_givewp_value_to_shared_log_level(): void {
		$this->mock_shared_log_level( 'error' );
		\WP_Mock::userFunction( 'give_get_option' )->with( 'venmo_log_level' )->andReturn( 'notice' );
		\WP_Mock::userFunction( 'give_update_option' )->once()->with( 'venmo_log_level', 'error' )->andReturn( true );

		$settings = ( new Gateway_Settings() )->register_settings( array() );

		$field = $this->find_log_level_field( $settings );
		$this->assertArrayHasKey( 'default', $field );
		$this->assertSame( 'error', $field['default'] );
	}

	/**
	 * @covers ::register_settings
	 */
	public function test_register_settings_does_nothing_outside_venmo_section(): void {
		$this->current_section = 'paypal';
		\WP_Mock::userFunction( 'give_update_option' )->never();

		$settings = ( new Gateway_Settings() )->register_settings( array() );

		$this->assertSame( array(), $settings );
	}

	/**
	 * @covers ::sanitize_log_level
	 */
	public function test_sanitize_log_level_updates_shared_option(): void {
		$this->mock_shared_log_level( 'notice' );
		\WP_Mock::userFunction( 'update_option' )->once()->with( 'bh_wp_venmo_gateway_log_level', 'debug' )->andReturn( true );

		$result = ( new Gateway_Settings() )->sanitize_log_level( 'debug' );

		$this->assertSame( 'debug', $result );
	}

	/**
	 * @covers ::sanitize_log_level
	 */
	public function test_sanitize_log_level_does_not_update_when_unchanged(): void {
		$this->mock_shared_log_level( 'info' );
		\WP_Mock::userFunction( 'update_option' )->never();

		$result = ( new Gateway_Settings() )->sanitize_log_level( 'info' );

		$this->assertSame( 'info', $result );
	}

	/**
	 * @covers ::sanitize_log_level
	 */
	public function test_sanitize_log_level_rejects_invalid_value(): void {
		$this->mock_shared_log_level( 'warning' );
		\WP_Mock::userFunction( 'update_option' )->never();

		$result = ( new Gateway_Settings() )->sanitize_log_level( 'verbose' );

		$this->assertSame( 'warning', $result );
	}
}
