<?php

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use BrianHenryIE\WC_Venmo_Gateway\WC_Order_Email_Reconcile\API\API as BH_WC_Order_Email_Reconcile;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class API implements API_Interface {

	use LoggerAwareTrait;

	/**
	 * The plugin settings.
	 *
	 * @var Settings_Interface
	 */
	protected Settings_Interface $settings;

	/**
	 * Instance of IMAP_Reconcile library to fetch emails and match with unpaid orders.
	 *
	 * @var BH_WC_Order_Email_Reconcile
	 */
	protected BH_WC_Order_Email_Reconcile $reconciler;

	/**
	 * @param BH_WC_Order_Email_Reconcile $reconciler
	 * @param Settings_Interface          $settings
	 * @param LoggerInterface             $logger
	 */
	public function __construct( BH_WC_Order_Email_Reconcile $reconciler, Settings_Interface $settings, LoggerInterface $logger ) {
		$this->logger     = $logger;
		$this->settings   = $settings;
		$this->reconciler = $reconciler;
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
}
