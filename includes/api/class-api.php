<?php
/**
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\API\API as BH_WP_Order_Email_Reconcile;
use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\API\Unpaid_Orders_Provider_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerAwareTrait;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerInterface;

class API implements API_Interface {

	use LoggerAwareTrait;

	/**
	 * @param BH_WP_Order_Email_Reconcile $reconciler Instance of IMAP_Reconcile library to fetch emails and match with unpaid orders.
	 * @param Settings_Interface          $settings The plugin settings.
	 * @param LoggerInterface             $logger
	 */
	public function __construct(
		protected BH_WP_Order_Email_Reconcile $reconciler,
		protected Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->logger = $logger;
	}

	/**
	 * Fetches emails from email servers for Venmo Gateways, parses them for payment data, reconciles with
	 * unpaid orders.
	 *
	 * @param ?int $since Unix time to check for emails since.
	 */
	public function check_for_payment_emails( $since = null ): void {

		// $this->imap->check_for_payment_emails( $since );
	}

	/**
	 * The reconcile library's aggregate provider of unpaid orders and donations.
	 *
	 * @see Unreconciled_Orders_Menu
	 */
	public function get_unpaid_orders_provider(): Unpaid_Orders_Provider_Interface {
		return $this->reconciler->get_unpaid_orders_provider();
	}
}
