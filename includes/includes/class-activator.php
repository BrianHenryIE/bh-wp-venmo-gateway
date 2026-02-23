<?php
/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/includes
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/includes
 * @author     Brian Henry <BrianHenryIE@gmail.com>
 */
class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate(): void {

		update_option( 'bh-wc-venmo-gateway-last-activated-time', time() );
	}
}
