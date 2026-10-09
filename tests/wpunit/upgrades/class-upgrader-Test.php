<?php
/**
 * Tests the installed version tracking and that upgrades run once.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Upgrade;

use JuicedPlugins\Venmo_Gateway_Pro\Includes\Activator;
use JuicedPlugins\Venmo_Gateway_Pro\WPUnit_Testcase;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Upgrade\Upgrader
 */
class Upgrader_Test extends WPUnit_Testcase {

	/**
	 * A new install has no saved version and no activation history.
	 *
	 * @covers ::do_upgrades
	 */
	public function test_new_install_saves_installed_version(): void {
		( new Upgrader( '4.3.0' ) )->do_upgrades();

		$this->assertSame( '4.3.0', get_option( 'juiced_venmo_gateway_pro_installed_version' ) );
	}

	/**
	 * Sites updating from before 4.3.0 have only the old activation times.
	 *
	 * @covers ::do_upgrades
	 * @covers ::get_installed_version
	 */
	public function test_upgrade_runs_when_old_activation_times_are_older(): void {
		update_option( 'bh_wp_venmo_gateway_activated_time', array( '2025-01-01T00:00:00+00:00' => '4.2.0' ) );
		update_option( 'bh_wp_venmo_gateway_log_level', 'debug' );

		( new Upgrader( '4.3.0' ) )->do_upgrades();

		$this->assertSame( 'debug', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertSame( '4.3.0', get_option( 'juiced_venmo_gateway_pro_installed_version' ) );
	}

	/**
	 * Sites which already ran the 4.3.0 upgrade before the installed version option existed.
	 *
	 * @covers ::do_upgrades
	 * @covers ::get_installed_version
	 */
	public function test_upgrade_skipped_when_activation_times_are_current(): void {
		update_option( 'juiced_venmo_gateway_pro_activated_time', array( '2025-01-01T00:00:00+00:00' => '4.3.0' ) );
		update_option( 'bh_wp_venmo_gateway_log_level', 'debug' );

		( new Upgrader( '4.3.0' ) )->do_upgrades();

		$this->assertSame( 'debug', get_option( 'bh_wp_venmo_gateway_log_level' ) );
		$this->assertFalse( get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertSame( '4.3.0', get_option( 'juiced_venmo_gateway_pro_installed_version' ) );
	}

	/**
	 * The saved installed version takes precedence over the activation times.
	 *
	 * @covers ::get_installed_version
	 */
	public function test_installed_version_option_takes_precedence(): void {
		update_option( 'juiced_venmo_gateway_pro_installed_version', '4.3.0' );
		update_option( 'juiced_venmo_gateway_pro_activated_time', array( '2025-01-01T00:00:00+00:00' => '4.2.0' ) );

		$this->assertSame( '4.3.0', ( new Upgrader( '4.4.0' ) )->get_installed_version() );
	}

	/**
	 * Updates without activation, e.g. 4.3.0 to 4.4.0, save the new version on the next load.
	 *
	 * @covers ::do_upgrades
	 */
	public function test_update_without_activation_saves_new_version(): void {
		update_option( 'juiced_venmo_gateway_pro_installed_version', '4.3.0' );
		update_option( 'bh_wp_venmo_gateway_log_level', 'debug' );

		( new Upgrader( '4.4.0' ) )->do_upgrades();

		$this->assertSame( '4.4.0', get_option( 'juiced_venmo_gateway_pro_installed_version' ) );
		// The 4.3.0 upgrade did not run again.
		$this->assertSame( 'debug', get_option( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * A downgrade leaves the saved version alone.
	 *
	 * @covers ::do_upgrades
	 */
	public function test_downgrade_does_not_change_installed_version(): void {
		update_option( 'juiced_venmo_gateway_pro_installed_version', '4.4.0' );

		( new Upgrader( '4.3.0' ) )->do_upgrades();

		$this->assertSame( '4.4.0', get_option( 'juiced_venmo_gateway_pro_installed_version' ) );
	}

	/**
	 * @covers ::register_hooks
	 */
	public function test_upgrades_run_on_plugins_loaded(): void {
		$upgrader = new Upgrader( '4.3.0' );
		$upgrader->register_hooks();

		$this->assertSame( 10, has_action( 'plugins_loaded', array( $upgrader, 'do_upgrades' ) ) );
	}

	/**
	 * Updating to 4.3.0 re-activates the plugin; the upgrade must run before the activation is recorded.
	 *
	 * @covers \JuicedPlugins\Venmo_Gateway_Pro\Includes\Activator::activate
	 */
	public function test_activation_runs_upgrade_before_recording_activation(): void {
		if ( ! defined( 'JUICED_VENMO_GATEWAY_PRO_VERSION' ) ) {
			define( 'JUICED_VENMO_GATEWAY_PRO_VERSION', '4.3.0' );
		}
		update_option( 'bh_wp_venmo_gateway_activated_time', array( '2025-01-01T00:00:00+00:00' => '4.2.0' ) );
		update_option( 'bh_wp_venmo_gateway_log_level', 'debug' );

		Activator::activate();

		$this->assertSame( 'debug', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertSame( constant( 'JUICED_VENMO_GATEWAY_PRO_VERSION' ), get_option( 'juiced_venmo_gateway_pro_installed_version' ) );
		$activated_times = get_option( 'juiced_venmo_gateway_pro_activated_time' );
		$this->assertIsArray( $activated_times );
		$this->assertSame( '4.2.0', $activated_times['2025-01-01T00:00:00+00:00'] );
	}
}
