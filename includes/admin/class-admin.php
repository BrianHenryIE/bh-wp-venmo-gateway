<?php
/**
 * Display an admin notice inviting the user to configure the plugin. Stops displaying after a week.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Admin;

use BrianHenryIE\WP_Venmo_Gateway\WPTRT\AdminNotices\Notices;
use BrianHenryIE\WP_Venmo_Gateway\WooCommerce\Venmo_Gateway;

/**
 * Checks that:
 * * last activated time is in the past week
 * * current_user_can('manage_options')
 * * gateway is not configured
 *
 * Class Admin
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */
class Admin {

	/**
	 * I don't really like this, but I guess it's composition over inheritance.
	 */
	protected Notices $notices;

	/**
	 * Initialize WPTRT\AdminNotices for presenting notices and handling dismissals.
	 *
	 * Load only on admin screens and AJAX requests.
	 *
	 * @hooked plugins_loaded
	 */
	public function init_notices(): void {

		if ( ! is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$this->notices = new Notices();
		$this->notices->boot();
	}

	/**
	 *
	 * @hooked admin_init
	 */
	public function add_setup_notice(): void {

		if ( ! is_admin() ) {
			return;
		}

		// e.g. during WooCommerce updating itself.
		if ( ! class_exists( \WC_Payment_Gateways::class ) ) {
			return;
		}

		/**
		 * TODO: update with underscored option name.
		 */
		$last_activated = get_option( 'bh-wp-venmo-gateway-last-activated-time', time() );

		// If last activation was longer than a week ago, return.
		if ( $last_activated < time() - WEEK_IN_SECONDS ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$payment_gateways = \WC_Payment_Gateways::instance()->payment_gateways();
		$venmo_gateways   = array();
		foreach ( $payment_gateways as $gateway ) {
			if ( $gateway instanceof Venmo_Gateway ) {
				if ( ! $gateway->is_configured() ) {
					$venmo_gateways[] = $gateway;
				}
			}
		}

		if ( 0 === count( $venmo_gateways ) ) {
			return;
		}

		if ( 1 === count( $venmo_gateways ) ) {
			// If there is only one Venmo gateway instance, link directly to it.
			$section = '&section=' . $venmo_gateways[0]->id;
		} else {
			// If there is more than one, link to the WooCommerce / Settings / Payments page filtered to the class type.
			$section = '&class=bh-wp-venmo-gateway';
		}

		$setting_link = admin_url( "admin.php?page=wc-settings&tab=checkout{$section}" );

		$id      = 'bh-wp-venmo-gateway-activation-configuration';
		$title   = '';
		$message = "Venmo Gateway needs to be configured. Please <a href=\"{$setting_link}\">visit the settings page</a> to enter the destination Venmo @username for payments to be sent.";

		$options = array(
			'capability' => 'manage_options',
		);

		$this->notices->add( $id, $title, $message, $options );
	}
}
