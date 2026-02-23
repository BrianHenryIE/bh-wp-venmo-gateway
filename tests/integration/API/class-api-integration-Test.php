<?php

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use BrianHenryIE\WC_Venmo_Gateway\WC_IMAP_Reconcile\API\IMAP_Mailbox_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WC_IMAP_Reconcile\IMAP_Reconcile;
use BrianHenryIE\WC_Venmo_Gateway\WP_Logger\Logger;

class API_Integration_Test extends \BrianHenryIE\WC_Venmo_Gateway\WPUnit_Testcase {

	public function test_live() {

		$this->markTestIncomplete();

		$order = new \WC_Order();
		$order->set_status( 'pending' );
		$order->set_payment_method( 'venmo' );

		$order->set_shipping_first_name( 'John' );
		$order->set_shipping_last_name( 'Doe' );
		$order->set_total( 123.45 );

		$order->save();

		/** @var IMAP_Mailbox_Settings_Interface[] $settings */
		$mailboxes   = array();
		$mailboxes[] = new class() implements IMAP_Mailbox_Settings_Interface {

			public function get_email_imap_server(): string {
				return '';
			}

			public function get_email_account_username(): string {
				return '';
			}

			public function get_email_account_password(): string {
				return '';
			}

			public function after_reconcile_email_action(): string {
				return 'nothing';
			}

			public function get_from_email_regex(): ?string {
				return null;
			}

			public function get_identifier_regex(): ?string {
				return null;
				// return '/venmo/';
			}
		};

		$settings = $this->make(
			Settings::class,
			array(
				'get_mailboxes' => $mailboxes,
			)
		);
		$logger   = Logger::instance( $settings );

		$time = time() - ( DAY_IN_SECONDS * 2 );
		update_option( 'bh-wc-venmo-gateway-last-imap-reconcile-run-time', $time );

		$imap = new IMAP_Reconcile( $settings, $logger );
		$api  = new API( $imap, $settings, $logger );

		// $api->check_for_payment_emails();
	}
}
