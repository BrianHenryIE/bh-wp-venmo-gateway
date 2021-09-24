<?php

namespace BrianHenryIE\WC_Venmo_Gateway\API;

use BrianHenryIE\WC_Venmo_Gateway\WC_IMAP_Reconcile\API\IMAP_Extract_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WC_IMAP_Reconcile\API\IMAP_Mailbox_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WC_IMAP_Reconcile\API\IMAP_Reconcile_Settings_Interface;
use BrianHenryIE\WC_Venmo_Gateway\WP_Logger\API\Logger_Settings_Interface;
use Psr\Log\LogLevel;
use BrianHenryIE\WC_Venmo_Gateway\WooCommerce\Venmo_Gateway;
use WC_Payment_Gateways;


class Settings implements Settings_Interface, IMAP_Reconcile_Settings_Interface, Logger_Settings_Interface {

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
		return LogLevel::INFO;
	}


	/**
	 * Used in the gateway to generate the settings page.
	 *
	 * @param string $gateway_id
	 *
	 * @return array
	 */
	public function get_form_fields( string $gateway_id ): array {

		$venmo_username_description = '';

		$form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'bh-wc-venmo-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable This Gateway', 'bh-wc-venmo-gateway' ),
				'default' => 'yes',
			),
			'title'          => array(
				'title'       => __( 'Title', 'bh-wc-venmo-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'bh-wc-venmo-gateway' ),
				'default'     => _x( 'Venmo', 'Method description here', 'bh-wc-venmo-gateway' ),
				'desc_tip'    => true,
			),
			'description'    => array(
				'title'       => __( 'Description', 'bh-wc-venmo-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'bh-wc-venmo-gateway' ) . " {$venmo_username_description}",
				'default'     => 'Use the Venmo app to pay for your order.',
				'desc_tip'    => true,
			),
			'venmo_username' => array(
				'title'       => __( 'Venmo Username', 'bh-wc-venmo-gateway' ),
				'type'        => 'text',
				'description' => __( 'The venmo username whose account the customer will be instructed to pay.', 'bh-wc-venmo-gateway' ),
				'desc_tip'    => false,
			),
		);

		// TODO: Display on settings page  (/add a link to show) what it will look like for the customer.

		return array_merge( $form_fields, $this->imap_reconcile_fields() );
	}

	/**
	 * TODO: Move this to the library.
	 *
	 * TODO: autocomplete="off"
	 * $value['custom_attributes'] as $attribute => $attribute_value
	 * $value['custom_attributes'] as 'autocomplete' => 'off'
	 * 'custom_attributes' => array( 'autocomplete' => 'off' )
	 *
	 * @see \WC_Admin_Settings::output_fields()
	 *
	 * @return array
	 */
	private function imap_reconcile_fields() {

		$form_fields = array(
			'email_server'                 => array(
				'title'             => __( 'Email server', 'bh-wc-venmo-gateway' ),
				'type'              => 'text',
				'description'       => __( 'IMAP server or IP address.', 'bh-wc-venmo-gateway' ),
				'desc_tip'          => true,
                'custom_attributes' => array( 'autocomplete' => 'off', 'data-lpignore'=>'true', 'data-form-type'=>'text' ),
				'id'                => 'email_server',
			),
			'email_username'               => array(
				'title'             => __( 'Email username', 'bh-wc-venmo-gateway' ),
				'type'              => 'text',
				'description'       => __( 'Login username for email address payment receipts are mailed to.', 'bh-wc-venmo-gateway' ),
				'desc_tip'          => true,
				'custom_attributes' => array( 'autocomplete' => 'off', 'data-lpignore'=>'true' ),
				'id'                => 'email_username',
			),
			'email_password'               => array(
				'title'             => __( 'Email account password', 'bh-wc-venmo-gateway' ),
				'type'              => 'password',
                'custom_attributes' => array( 'autocomplete' => 'off', 'data-lpignore'=>'true' ),
				'id'                => 'email_password',
			),

			'after_reconcile_email_action' => array(
				'title'       => __( 'After reconcile action', 'bh-wc-venmo-gateway' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Action to take after an email is matched to an order.', 'bh-wc-venmo-gateway' ),
				'default'     => 'mark_read',
				'desc_tip'    => true,
				'options'     => array(
					'nothing'   => __( 'Nothing', 'bh-wc-venmo-gateway' ),
					'mark_read' => __( 'Mark email read', 'bh-wc-venmo-gateway' ),
					'delete'    => __( 'Delete email', 'bh-wc-venmo-gateway' ),
				),
			),
		);

		return $form_fields;
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
		return '2.2.0';
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

	public function get_mailboxes(): array {
		$mailboxes = array();
		foreach ( $this->get_payment_method_ids() as $gateway_id ) {

			$mailboxes[] = new class( $gateway_id ) implements IMAP_Mailbox_Settings_Interface {

				protected string $gateway_id;

				public function __construct( string $gateway_id ) {
					$this->gateway_id = $gateway_id;
				}

				protected function get_gateway_id(): string {
					return $this->gateway_id;
				}

				/**
				 * Helper function to return settings saved by WooCommerce.
				 */
				protected function get_woo_settings( $setting ) {

					$gateway_id = $this->get_gateway_id();

					$settings_id = "bh-wc-venmo-gateway_{$gateway_id}_settings";

					$woo_settings = get_option( $settings_id );

					if ( false === $woo_settings ) {
						return false;
					}

					if ( isset( $woo_settings[ $setting ] ) ) {
						return $woo_settings[ $setting ];
					}

					return false;
				}

				public function get_email_imap_server(): string {
					return $this->get_woo_settings( 'email_server' );
				}

				public function get_email_account_username(): string {
					return $this->get_woo_settings( 'email_username' );
				}

				public function get_email_account_password(): string {
					return $this->get_woo_settings( 'email_password' );
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
					$action = $this->get_woo_settings( 'after_reconcile_email_action' );

					if ( 'production' !== wp_get_environment_type() ) {
						return 'nothing';
					}

					return in_array( $action, array( 'nothing', 'mark_read', 'delete' ), true ) ? $action : 'mark_read';
				}

				/**
				 * Forwarded emails do not preserve the From email address.
				 * Otherwise should be venmo@venmo.com.
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
	 * @return IMAP_Extract_Settings_Interface[]
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
	public function get_customer_payment_id_meta_key() {
		return Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY;
	}

	public function get_plugin_name(): string {
		return 'Venmo Gateway';
	}

	public function get_plugin_basename(): string {
		return 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php';
	}


}
