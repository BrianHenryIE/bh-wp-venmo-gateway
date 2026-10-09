<?php
/**
 * Fired during plugin activation
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

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

		$times = get_option( 'juiced_venmo_gateway_pro_activated_time', array() );

		$times[ wp_date( DateTimeInterface::ATOM ) ] = constant( 'JUICED_VENMO_GATEWAY_PRO_VERSION' );

		update_option( 'juiced_venmo_gateway_pro_activated_time', $times );
	}
}
