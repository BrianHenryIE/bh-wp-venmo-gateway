<?php
/**
 * Fired during plugin activation
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

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

		update_option( 'bh-wc-venmo-gateway-last-activated-time', time() );
	}
}
