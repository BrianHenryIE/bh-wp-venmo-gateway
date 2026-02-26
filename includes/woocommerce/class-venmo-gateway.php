<?php
/**
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\WC_Order_Email_Reconcile\WooCommerce\Credentials_Settings_Fields;
use BrianHenryIE\WC_Venmo_Gateway\API\Settings;
use BrianHenryIE\WC_Venmo_Gateway\API\Settings_Interface;
use WC_Order;
use WC_Payment_Gateway;

class Venmo_Gateway extends WC_Payment_Gateway {

	public $id = 'venmo';

	/**
	 * This is overwritten to add the destination account username.
	 *
	 * @see Payment_Gateways::format_admin_gateway_name()
	 */
	public $method_title = 'Venmo';

	public $title = 'Venmo';

	const CUSTOMER_VENMO_USERNAME_META_KEY = '_customer-venmo-username';

	// The meta key to save to individual orders.
	const STORE_VENMO_USERNAME_META_KEY = '_destination-account-venmo-username';

	/**
	 * @var Settings_Interface
	 */
	protected Settings_Interface $plugin_settings;

	public function __construct() {

		$this->plugin_settings = new Settings();

		// Is this a good or bad idea?
		$this->plugin_id = "{$this->plugin_settings->get_plugin_slug()}_";

		$this->icon = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php' );

		$this->has_fields = true;

		/**
		 * This is overwritten to add the destination account username.
		 *
		 * @see Venmo_Gateway::get_method_description()
		 */
		$this->method_description = 'Prompts the customer for their Venmo @username and instructs them to send payment the specified account.';

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->description = $this->get_option( 'description' );

		// Save the wp-admin configuration form options.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Save the customer Venmo username to the order meta as the order is created (shortcode checkout).
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_payment_type_meta_data' ), 10, 2 );

		// Save the customer Venmo username for blocks checkout (Store API).
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_blocks_checkout_meta_data' ) );

		$this->enabled = ( 'yes' === $this->enabled & $this->is_configured() ) ? 'yes' : 'no';
	}

	/**
	 * Check is the destination Venmo account username entered so the gateway is ready to use.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_option( 'store_venmo_username' ) );
	}

	/**
	 * The wp-admin configuration form.
	 */
	public function init_form_fields(): void {

		$store_venmo_username_description = '';

		$form_fields = array(
			'enabled'              => array(
				'title'   => __( 'Enable/Disable', 'bh-wc-venmo-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable This Gateway', 'bh-wc-venmo-gateway' ),
				'default' => 'yes',
			),
			'title'                => array(
				'title'       => __( 'Title', 'bh-wc-venmo-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'bh-wc-venmo-gateway' ),
				'default'     => _x( 'Venmo', 'Method description here', 'bh-wc-venmo-gateway' ),
				'desc_tip'    => true,
			),
			'description'          => array(
				'title'       => __( 'Description', 'bh-wc-venmo-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'bh-wc-venmo-gateway' ) . " {$store_venmo_username_description}",
				'default'     => 'Use the Venmo app to pay for your order.',
				'desc_tip'    => true,
			),
			'store_venmo_username' => array(
				'title'       => __( 'Venmo Username', 'bh-wc-venmo-gateway' ),
				'type'        => 'text',
				'description' => __( 'The venmo username whose account the customer will be instructed to pay.', 'bh-wc-venmo-gateway' ),
				'desc_tip'    => false,
			),
		);

		// TODO: Display on settings page  (/add a link to show) what it will look like for the customer.

		$credentials_fields = new Credentials_Settings_Fields();
		$form_fields        = $credentials_fields->append_imap_reconcile_fields( $form_fields );

		$this->form_fields = $form_fields;
	}

	/**
	 * Prints the form displayed on the checkout.
	 * i.e. a simple HTML text input for the Venmo username.
	 */
	public function payment_fields(): void {

		// This just prints the description.
		parent::payment_fields();

		$value = null; // TODO Try pre-populate the user's email address or Venmo if they have paid in the past.

		woocommerce_form_field(
			self::CUSTOMER_VENMO_USERNAME_META_KEY,
			array(
				'label'       => 'Enter your Venmo username:',
				'placeholder' => 'Venmo username',
				'maxlength'   => 255,
				'required'    => true,
			)
		);
	}

	/**
	 * Save the Venmo username as the order is created.
	 *
	 * @see woocommerce_checkout_update_order_meta
	 *
	 * @hooked woocommerce_checkout_create_order
	 * @see WC_Checkout::create_order()
	 * @see WC_Checkout::get_posted_data()
	 *
	 * @param WC_Order $order The newly created WooCommerce order
	 * @param string[] $data
	 */
	public function save_order_payment_type_meta_data( WC_Order $order, array $data ): void {

		if ( $data['payment_method'] !== $this->id || ! isset( $_POST[ self::CUSTOMER_VENMO_USERNAME_META_KEY ] ) ) {
			return;
		}

		$customer_venmo_username = esc_attr( $_POST[ self::CUSTOMER_VENMO_USERNAME_META_KEY ] );

		// TODO: Add to the WP User's account meta too.
		$order->add_meta_data( self::CUSTOMER_VENMO_USERNAME_META_KEY, $customer_venmo_username, true );

		$store_venmo_username = $this->get_option( 'store_venmo_username' );
		$order->add_meta_data( self::STORE_VENMO_USERNAME_META_KEY, $store_venmo_username, true );

		$order->save();
	}

	/**
	 * Save the Venmo username from the blocks checkout (Store API).
	 *
	 * The blocks checkout sends payment method data via the Store API, which makes it
	 * available in the request body. This hook fires after the order is processed.
	 *
	 * @hooked woocommerce_store_api_checkout_order_processed
	 *
	 * @param WC_Order $order The WooCommerce order.
	 */
	public function save_blocks_checkout_meta_data( WC_Order $order ): void {

		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		// Already saved by the shortcode checkout hook.
		if ( $order->meta_exists( self::CUSTOMER_VENMO_USERNAME_META_KEY ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$request_body = file_get_contents( 'php://input' );
		if ( ! $request_body ) {
			return;
		}

		$request_data = json_decode( $request_body, true );
		$payment_data = $request_data['payment_data'] ?? array();

		$customer_venmo_username = '';
		foreach ( $payment_data as $item ) {
			if ( isset( $item['key'] ) && self::CUSTOMER_VENMO_USERNAME_META_KEY === $item['key'] ) {
				$customer_venmo_username = sanitize_text_field( $item['value'] );
				break;
			}
		}

		if ( empty( $customer_venmo_username ) ) {
			return;
		}

		$order->add_meta_data( self::CUSTOMER_VENMO_USERNAME_META_KEY, $customer_venmo_username, true );

		$store_venmo_username = $this->get_option( 'store_venmo_username' );
		$order->add_meta_data( self::STORE_VENMO_USERNAME_META_KEY, $store_venmo_username, true );

		$order->save();
	}

	/**
	 * On-Hold – Awaiting payment – stock is reduced, but you need to confirm payment.
	 *
	 * @see https://docs.woocommerce.com/document/managing-orders/
	 *
	 * @param int $order_id
	 * @return string[]
	 */
	public function process_payment( $order_id ): array {

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof WC_Order ) ) {
			// TODO: What are the correct return values for false|WC_Order_Refund?
			return array();
		}

		$customer_venmo_account    = $order->get_meta( self::CUSTOMER_VENMO_USERNAME_META_KEY, true );
		$destination_venmo_account = $order->get_meta( self::STORE_VENMO_USERNAME_META_KEY, true );

		/**
		 * Template: `https://venmo.com/{username}?txn=pay&amount={amount}&note={note}`.
		 */
		$venmo_payment_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( $destination_venmo_account ),
			rawurlencode( $order->get_total() ),
			rawurlencode( 'order ' . $order_id )
		);

		$note = sprintf(
			'Awaiting Venmo payment of $%s from <a target="_blank" href="https://venmo.com/%s">@%s</a> to <a target="_blank" href="%s">@%s</a>.',
			$order->get_total(),
			$customer_venmo_account,
			$customer_venmo_account,
			$venmo_payment_url,
			$destination_venmo_account,
		);

		$order->update_status( 'on-hold', $note );

		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );

		// Empty cart.
		WC()->cart->empty_cart();

		// Redirect to Thank You page.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Output the gateway settings screen.
	 *
	 * Overrides the parents.
	 *
	 * @see WC_Payment_Gateway::admin_options()
	 * @see WC_Settings_API::admin_options()
	 */
	public function admin_options(): void {
		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );

		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
	}

	/**
	 * Return the gateway's title.
	 *
	 * This is displayed on the checkout to the customer.
	 * Also displayed on the admin order page.
	 *
	 * @see WC_Payment_Gateway::get_title()
	 *
	 * @return string
	 */
	public function get_title(): string {

		$title = $this->title;

		/**
		 * Hide the title "Venmo" or "venmo" at the checkout so only the logo image is displayed.
		 */
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$title = in_array( $title, array( 'Venmo', 'venmo' ), true )
				? ''
				: $title;
		}

		/**
		 * In admin order UI, show the username associated with the gateway.
		 * wp-admin/admin.php?page=wc-orders&action=edit&id=128
		 *
		 * TODO: This should really pull fom the order object iself, not the gateway. This block of code is in the wrong class.
		 *
		 * Order #128 details
		 * Payment via Venmo: @brianhenryie. Customer IP: 192.168.1.2
		 */
		if ( function_exists( 'get_current_screen' ) ) {
			global $pagenow;
			global $plugin_page;

			$store_venmo_username = $this->get_option( 'store_venmo_username' );

			if ( ! empty( $store_venmo_username ) && 'admin.php' === $pagenow && 'wc-orders' === $plugin_page ) {
				$title = "Venmo: @{$store_venmo_username}";
			}
		}

		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}

	/**
	 * Add the destination venmo username to the admin ui gateway title (particularly to distinguish multiple instances).
	 *
	 * TODO: The <i> are showing on the individual gateway settings page: /wp-admin/admin.php?page=wc-settings&tab=checkout&section=venmo
	 *
	 * @return string
	 */
	public function get_method_title() {
		$method_title = $this->method_title;

		$store_venmo_username = $this->get_option( 'store_venmo_username' );

		if ( empty( $store_venmo_username ) ) {
			return $method_title;
		}

		// Don't format it on the gateway's page itself.
		if ( isset( $_GET['tab'] ) && 'checkout' === $_GET['tab'] && ! isset( $_GET['section'] ) ) {
			$method_title = "{$method_title} – <i>{$store_venmo_username}</i>";
		} else {
			$method_title = "{$method_title} – {$store_venmo_username}";
		}

		/**
		 * Filter the method title.
		 *
		 * @param string $title Method title.
		 * @param WC_Payment_Gateway $this Payment gateway instance.
		 * @return string
		 */
		return apply_filters( 'woocommerce_gateway_method_title', $method_title, $this );
	}

	/**
	 * Return the description for admin screens.
	 *
	 * e.g. Description column of WooCommerce/Settings/Payments.
	 *
	 * Overrides:
	 *
	 * @see WC_Payment_Gateway::get_method_description()
	 *
	 * @return string
	 */
	public function get_method_description(): string {

		$method_description = $this->method_description;

		$store_venmo_username = $this->get_option( 'store_venmo_username' );

		if ( ! empty( $store_venmo_username ) ) {

			$method_description = "Prompts the customer for their Venmo @username and instructs them to send payment to: <a href=\"https://venmo.com/{$store_venmo_username}\">@{$store_venmo_username}</a>";
		}
		return apply_filters( 'woocommerce_gateway_method_description', $method_description, $this );
	}
}
