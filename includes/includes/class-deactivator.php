<?php
/**
 * Fired during plugin deactivation
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 */
	public static function deactivate(): void {

		wp_clear_scheduled_hook( Cron::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );
	}
}
