<?php
/**
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use BrianHenryIE\WC_Venmo_Gateway\WC_Order_Email_Reconcile\Email_Extract_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WC_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Venmo_Gateway;
use BrianHenryIE\WC_Venmo_Gateway\WP_Logger\Logger_Settings_Trait;
use BrianHenryIE\WC_Venmo_Gateway\WP_Logger\WooCommerce_Logger_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WP_Mailboxes\Account_Credentials_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WP_Mailboxes\API\Ddeboer_Imap\IMAP_Credentials_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WP_Mailboxes\BH_WP_Mailboxes_Settings_Defaults_Trait;
use BrianHenryIE\WC_Venmo_Gateway\WP_Mailboxes\Mailbox_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WP_Mailboxes\Mailbox_Settings_Defaults_Trait;
use BrianHenryIE\WC_Venmo_Gateway\Psr\Log\LogLevel;
use WC_Payment_Gateways;

class Settings implements Settings_Interface, Email_Reconcile_Settings_Interface, WooCommerce_Logger_Settings_Interface {
	use BH_WP_Mailboxes_Settings_Defaults_Trait;
	use Logger_Settings_Trait;

	/**
	 * @see Logger_Settings_Interface
	 * @see IMAP_Reconcile_Settings_Interface
	 *
	 * @return string
	 */
	public function get_plugin_slug(): string {
		return 'bh-wc-venmo-gateway';
	}

	/**
	 * TODO: Add to WooCommerce settings.
	 *
	 * @return string
	 */
	public function get_log_level(): string {
		return LogLevel::DEBUG;
	}



	/**
	 * This bool determines if the cron job is created (if absent) or deleted (if present).
	 *
	 * TODO: use the actual settings! (validate...)
	 * TODO: add filter.
	 *
	 * @return bool
	 */
	public function is_imap_reconcile_enabled(): bool {
		return true;
	}

	public function get_plugin_version(): string {
		return '3.1.1';
	}

	/**
	 *
	 *
	 * @return array|string
	 */
	public function get_payment_method_ids(): array {

		// TODO: Can this be run before woocommerce_loaded?
		// If not?... cache it.
		// Print a warning in the logs.

		// if( ! did_action( 'woocommerce_payment_gateways' ) ) { rteturn

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
	 * Name for the emails' custom post type, e.g. "My Plugin Emails".
	 *
	 * Trait will automatically convert this to "my-plugin-emails" and "my_plugin_emails" where appropriate,
	 * using `sanitize_title` and additionally `str_replace('-','_'...)` respectively.
	 *
	 * Should usually be one cpt per plugin. But there can be more than one mailbox per plugin.
	 * This should be hard-coded, and not derived from user input (e.g. mailbox name).
	 *
	 * Max.  length 20 characters.
	 */
	public function get_cpt_friendly_name(): string {
		return 'Venmo Payment Emails';
	}



	/**
	 * Helper function to return settings saved by WooCommerce.
	 *
	 * @param string $setting
	 * @return mixed
	 */
	protected function get_woo_settings( $gateway_id, string $setting ) {

		$settings_id = "bh-wc-venmo-gateway_{$gateway_id}_settings";

		$woo_settings = get_option( $settings_id, array() );

		return $woo_settings[ $setting ] ?? false;
	}


	/**
	 * The settings for the mailboxes to be checked.
	 *
	 * @return Mailbox_Settings_Interface[]
	 */
	public function get_configured_mailbox_settings(): array {

		$mailboxes = array();
		foreach ( $this->get_payment_method_ids() as $gateway_id ) {

			$email_imap_server      = $this->get_woo_settings( $gateway_id, 'email_server' );
			$email_account_username = $this->get_woo_settings( $gateway_id, 'email_username' );
			$email_account_password = $this->get_woo_settings( $gateway_id, 'email_password' );

			if ( empty( $email_imap_server ) || empty( $email_account_username ) || empty( $email_account_password ) ) {
				continue;
			}

			$action = $this->get_woo_settings( $gateway_id, 'after_reconcile_email_action' );

			$mailboxes[] = new class( $gateway_id, $email_imap_server, $email_account_username, $email_account_password, $action ) implements Mailbox_Settings_Interface {
				use Mailbox_Settings_Defaults_Trait;

				protected Account_Credentials_Interface $credentials;

				public function __construct( protected string $gateway_id, $email_imap_server, $email_account_username, $email_account_password, protected string $action ) {
					$imap_credentials = new class($email_imap_server, $email_account_username, $email_account_password) implements IMAP_Credentials_Interface {

						public function __construct( protected string $email_imap_server, protected string $email_account_username, protected string $email_account_password ) {
						}

						public function get_email_imap_server(): string {
							return $this->email_imap_server;
						}

						public function get_email_account_username(): string {
							return $this->email_account_username;
						}

						public function get_email_account_password(): string {
							return $this->email_account_password;
						}
					};

					$this->credentials = $imap_credentials;
				}

				/**
				 * Should the email be deleted after it is reconciled?
				 *
				 * Default: mark_read.
				 * On staging sites: nothing.
				 *
				 * @return string nothing|mark_read|delete
				 */
				public function after_reconcile_email_action(): string {

					if ( 'production' !== wp_get_environment_type() ) {
						return 'nothing';
					}

					return in_array( $this->action, array( 'nothing', 'mark_read', 'delete' ), true ) ? $this->action : 'mark_read';
				}

				/**
				 * Do not filter to a specific email address.
				 *
				 * @return null
				 */
				public function get_from_email_regex(): ?string {
					return null;
				}

				/**
				 * Ignore emails that don't mention https://venmo.com/
				 *
				 * @return string
				 */
				public function get_identifier_regex(): ?string {
					return '/https:\/\/venmo.com\//';
				}


				public function get_account_unique_friendly_name(): string {
					return $this->gateway_id;
				}

				public function get_credentials(): Account_Credentials_Interface {
					return $this->credentials;
				}
			};

		}
		return $mailboxes;
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

		return $patterns;
	}

	/**
	 * Tell the IMAP reconcile how to find the customers' Venmo usernames.
	 *
	 * @return string
	 */
	public function get_customer_payment_id_meta_key(): ?string {
		return Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY;
	}

	public function get_plugin_name(): string {
		return 'Venmo Gateway';
	}

	public function get_plugin_basename(): string {
		return defined( 'BH_WC_VENMO_GATEWAY_BASENAME' ) ? BH_WC_VENMO_GATEWAY_BASENAME : 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php';
	}
}
