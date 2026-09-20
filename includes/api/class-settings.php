<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\BH_WP_Mailboxes_Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Venmo_Gateway;
use BrianHenryIE\WP_Venmo_Gateway\WP_Logger\Logger_Settings_Trait;
use BrianHenryIE\WP_Venmo_Gateway\WP_Logger\WooCommerce_Logger_Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\BH_WP_Mailboxes_Settings_Defaults_Trait;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LogLevel;
use WC_Payment_Gateways;

class Settings implements Settings_Interface, WooCommerce_Logger_Settings_Interface {
	use BH_WP_Mailboxes_Settings_Defaults_Trait, Logger_Settings_Trait {
		BH_WP_Mailboxes_Settings_Defaults_Trait::get_cli_base insteadof Logger_Settings_Trait;
		BH_WP_Mailboxes_Settings_Defaults_Trait::get_cli_base as mailboxes_cli_base;
		Logger_Settings_Trait::get_cli_base as logger_cli_base;
	}

	/**
	 * @see Logger_Settings_Interface
	 * @see IMAP_Reconcile_Settings_Interface
	 */
	public function get_plugin_slug(): string {
		return 'bh-wp-venmo-gateway';
	}

	/**
	 * The log level for the plugin, configured on the WooCommerce gateway settings page.
	 *
	 * `wp option update bh_wp_venmo_gateway_log_level`
	 *
	 * @see Venmo_Gateway::update_plugin_log_level_on_settings_save()
	 */
	public function get_log_level(): string {
		return get_option( 'bh_wp_venmo_gateway_log_level', LogLevel::NOTICE );
	}

	/**
	 * This bool determines if the cron job is created (if absent) or deleted (if present).
	 *
	 * TODO: use the actual settings! (validate...)
	 * TODO: add filter.
	 */
	public function is_imap_reconcile_enabled(): bool {
		return true;
	}

	public function get_plugin_version(): string {
		return '4.3.0';
	}

	/**
	 *
	 *
	 * @return string[]
	 */
	public function get_payment_method_ids(): array {

		// TODO: Can this be run before woocommerce_loaded?
		// If not?... cache it.
		// Print a warning in the logs.

		// if( ! did_action( 'woocommerce_payment_gateways' ) ) { return

		if ( class_exists( WC_Payment_Gateways::class ) ) {
			$gateway_subclasses = array();
			$payment_gateways   = WC_Payment_Gateways::instance()->payment_gateways();

			foreach ( $payment_gateways as $payment_gateway_instance ) {

				if ( $payment_gateway_instance instanceof Venmo_Gateway ) {

					$gateway_subclasses[] = $payment_gateway_instance->id;

				}
			}

			return $gateway_subclasses;
		} else {
			return array( 'venmo' );
		}
	}


	/**
	 * Name for the emails' custom post type, e.g. 'My Plugin Emails'.
	 *
	 * The trait converts this to 'venmo-payment-emails' / 'venmo_payment_emails' where appropriate,
	 * using `sanitize_title` and additionally `str_replace('-','_'...)` respectively.
	 *
	 * Should usually be one cpt per plugin. But there can be more than one mailbox per plugin.
	 * This should be hard-coded, and not derived from user input (e.g. mailbox name).
	 *
	 * Max. length 20 characters.
	 *
	 * @see BH_WP_Mailboxes_Settings_Interface::get_emails_cpt_friendly_name()
	 */
	public function get_emails_cpt_friendly_name(): string {
		return 'Venmo Payment Emails';
	}

	/**
	 * Display name for the email-accounts custom post type.
	 *
	 * Max. length 20 characters.
	 *
	 * @see BH_WP_Mailboxes_Settings_Interface::get_email_accounts_cpt_friendly_name()
	 */
	public function get_email_accounts_cpt_friendly_name(): string {
		return 'Venmo Email Accounts';
	}

	/**
	 * The regex patterns for parsing the emails.
	 *
	 * Multiple sets of patterns to extra data from the emails can be defined.
	 *
	 * TODO Filter
	 *
	 * @return Email_Extract_Settings_Interface[]
	 */
	public function get_patterns(): array {

		$patterns = array();

		$patterns[] = new Pattern_1();
		$patterns[] = new Pattern_2();
		$patterns[] = new Pattern_3();

		return $patterns;
	}

	/**
	 * Tell the IMAP reconcile how to find the customers' Venmo usernames.
	 */
	public function get_customer_payment_id_meta_key(): ?string {
		return Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY;
	}

	/**
	 * Used on the logs page to show a nice name.
	 */
	public function get_plugin_name(): string {
		return 'Venmo Gateway';
	}

	/**
	 * Used in the logs library; used for `plugins.php` links.
	 */
	public function get_plugin_basename(): string {
		return defined( 'BH_WP_VENMO_GATEWAY_BASENAME' )
			? constant( 'BH_WP_VENMO_GATEWAY_BASENAME' )
			: 'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php';
	}

	/**
	 * @see BH_WP_Mailboxes_Settings_Interface::get_rest_namespace()
	 */
	public function get_rest_namespace(): ?string {
		return 'bh-wp-venmo-gateway';
	}
}
