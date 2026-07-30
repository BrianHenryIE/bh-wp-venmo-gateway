<?php
/**
 * Fired during plugin activation
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

use DateTimeInterface;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

	/**
	 * Record each time the plugin is activated.
	 */
	public static function activate(): void {

		$times = get_option( 'bh_wp_venmo_gateway_activated_time', array() );

		$times[ wp_date( DateTimeInterface::ATOM ) ] = constant( 'BH_WP_VENMO_GATEWAY_VERSION' );

		update_option( 'bh_wp_venmo_gateway_activated_time', $times );
	}
}
