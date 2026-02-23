<?php
/**
 * Fired during plugin deactivation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/includes
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/includes
 * @author     Brian Henry <BrianHenryIE@gmail.com>
 */
class Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate(): void {

		wp_clear_scheduled_hook( Cron::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK );
	}
}
