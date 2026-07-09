<?php
/**
 * Fired during plugin activation
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

	/**
	 * Short Description. (use period)
	 */
	public static function activate(): void {

		update_option( 'bh_wp_venmo_gateway_first_activated_time', time() );

		delete_option( 'bh-wp-venmo-gateway-last-activated-time' );
		update_option( 'bh_wp_venmo_gateway_last_activated_time', time() );
	}
}
