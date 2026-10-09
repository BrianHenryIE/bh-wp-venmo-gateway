<?php
/**
 * Runs each version's upgrade routine once, tracking the installed version in an option.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Upgrade;

use JuicedPlugins\Venmo_Gateway_Pro\Includes\Activator;

/**
 * Compares the saved installed version with the plugin version and runs the upgrades in between.
 */
class Upgrader {

	/**
	 * The version of the plugin whose upgrades have last run.
	 */
	const INSTALLED_VERSION_OPTION_NAME = 'juiced_venmo_gateway_pro_installed_version';

	/**
	 * Each activation is recorded as `[ ATOM datetime => plugin version ]`.
	 *
	 * Only used to find the previous version for sites updating from before the installed version option existed.
	 *
	 * @see Activator::activate()
	 */
	const ACTIVATED_TIME_OPTION_NAMES = array(
		'juiced_venmo_gateway_pro_activated_time',
		'bh_wp_venmo_gateway_activated_time',
	);

	/**
	 * Constructor.
	 *
	 * @param string $plugin_version The version of the plugin code now running.
	 */
	public function __construct(
		protected string $plugin_version,
	) {
	}

	/**
	 * Check on every load, because updates that do not rename the plugin do not run the activation hook.
	 */
	public function register_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'do_upgrades' ) );
	}

	/**
	 * Run the upgrades for versions after the installed version, then save the plugin version as installed.
	 *
	 * Also called directly by the activator, before it records the activation.
	 *
	 * @hooked plugins_loaded
	 * @see Upgrader::register_hooks()
	 * @see Activator::activate()
	 */
	public function do_upgrades(): void {
		$installed_version = $this->get_installed_version();

		if ( version_compare( $installed_version, $this->plugin_version, '>=' ) ) {
			// Save the version found in the activation times, so it is not needed again.
			if ( false === get_option( self::INSTALLED_VERSION_OPTION_NAME ) ) {
				update_option( self::INSTALLED_VERSION_OPTION_NAME, $installed_version );
			}
			return;
		}

		if ( version_compare( $installed_version, V430::VERSION, '<' ) ) {
			( new V430() )->rename_options_once();
		}

		update_option( self::INSTALLED_VERSION_OPTION_NAME, $this->plugin_version );
	}

	/**
	 * The saved installed version.
	 *
	 * Before the option existed, the highest version recorded in the activation times, under the old or new name,
	 * or `0.0.0` when there are none, e.g. a new install.
	 */
	public function get_installed_version(): string {
		$installed_version = get_option( self::INSTALLED_VERSION_OPTION_NAME );
		if ( is_string( $installed_version ) && '' !== $installed_version ) {
			return $installed_version;
		}

		$installed_version = '0.0.0';
		foreach ( self::ACTIVATED_TIME_OPTION_NAMES as $activated_time_option_name ) {
			$activated_times = get_option( $activated_time_option_name, array() );
			foreach ( is_array( $activated_times ) ? $activated_times : array() as $version ) {
				if ( is_string( $version ) && version_compare( $version, $installed_version, '>' ) ) {
					$installed_version = $version;
				}
			}
		}
		return $installed_version;
	}
}
