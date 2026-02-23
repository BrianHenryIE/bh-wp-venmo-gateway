<?php
/**
 * AJAX endpoint to set WooCommerce customer billing and shipping data in the session.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Development_Plugin\Ajax;

use WC_Customer;

/**
 * Set customer data in WooCommerce session for testing.
 */
class WooCommerce_Customer {

	/**
	 * Add hooks to register the REST endpoint and AJAX handler.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_e2e_set_customer_data', $this->ajax_set_customer_data( ... ) );
		add_action( 'wp_ajax_nopriv_e2e_set_customer_data', $this->ajax_set_customer_data( ... ) );
		add_action( 'wp_ajax_e2e_add_to_cart', $this->ajax_add_to_cart( ... ) );
		add_action( 'wp_ajax_nopriv_e2e_add_to_cart', $this->ajax_add_to_cart( ... ) );
		add_filter( 'woocommerce_get_script_data', $this->add_nonce_to_woocommerce_params( ... ) );
	}

	/**
	 * Add our custom nonce to woocommerce_params for easy access.
	 *
	 * @param false|array{ajax_url:string, wc_ajax_url:string, i18n_view_cart:string, cart_url:string, is_cart:bool, cart_redirect_after_add:string} $params WooCommerce params.
	 * @return array{e2e_set_customer_data_nonce:string}
	 */
	public function add_nonce_to_woocommerce_params( $params ): array {
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$params['e2e_set_customer_data_nonce'] = wp_create_nonce( 'e2e-set-customer-data' );
		$params['e2e_add_to_cart_nonce']       = wp_create_nonce( 'e2e-add-to-cart' );
		return $params;
	}

	/**
	 * AJAX handler to set customer data (similar to WC_AJAX::update_order_review but for all fields).
	 */
	public function ajax_set_customer_data(): void {
		check_ajax_referer( 'e2e-set-customer-data', 'security' );

		if ( ! function_exists( 'WC' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not installed' ) );
			return;
		}

		// Force WooCommerce initialization if not already done.
		if ( ! did_action( 'woocommerce_init' ) ) {
			WC();
		}

		// Initialize cart if needed (even if empty).
		if ( is_null( WC()->cart ) ) {
			WC()->initialize_cart();
		}

		// Initialize session if needed.
		if ( is_null( WC()->session ) || ! WC()->session->has_session() ) {
			WC()->initialize_session();
			// Force set a session cookie.
			WC()->session->set_customer_session_cookie( true );
		}

		/**
		 * Initialize customer if needed.
		 * (WooCommerce's class-woocommerce.php:122 has the incorrect return type-hint).
		 *
		 * @var ?WC_Customer $wc_customer
		 */
		$wc_customer = WC()->customer;
		if ( is_null( $wc_customer ) ) {
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}

		$billing_props  = array();
		$shipping_props = array();

		// Billing fields.
		$billing_fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' );
		foreach ( $billing_fields as $field ) {
			if ( isset( $_POST[ 'billing_' . $field ] ) && is_string( $_POST[ 'billing_' . $field ] ) ) {
				$billing_props[ 'billing_' . $field ] = sanitize_text_field( wp_unslash( $_POST[ 'billing_' . $field ] ) );
			}
		}

		// Shipping fields.
		$shipping_fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );
		foreach ( $shipping_fields as $field ) {
			if ( isset( $_POST[ 'shipping_' . $field ] ) && is_string( $_POST[ 'shipping_' . $field ] ) ) {
				$shipping_props[ 'shipping_' . $field ] = sanitize_text_field( wp_unslash( $_POST[ 'shipping_' . $field ] ) );
			}
		}

		// Set the properties.
		if ( ! empty( $billing_props ) ) {
			WC()->customer->set_props( $billing_props );
		}

		if ( ! empty( $shipping_props ) ) {
			WC()->customer->set_props( $shipping_props );
		}

		// Save to session.
		WC()->customer->save();

		// Return the data for verification.
		wp_send_json_success(
			array(
				'message'        => 'Customer data saved',
				'billing_props'  => $billing_props,
				'shipping_props' => $shipping_props,
				'saved_data'     => array(
					'billing_first_name' => WC()->customer->get_billing_first_name(),
					'billing_last_name'  => WC()->customer->get_billing_last_name(),
					'billing_email'      => WC()->customer->get_billing_email(),
					'billing_phone'      => WC()->customer->get_billing_phone(),
				),
			)
		);
	}

	/**
	 * AJAX handler to add a product to the WooCommerce cart by title.
	 */
	public function ajax_add_to_cart(): void {
		check_ajax_referer( 'e2e-add-to-cart', 'security' );

		$product_name = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		if ( empty( $product_name ) ) {
			wp_send_json_error( array( 'message' => 'product_name is required' ) );
			return;
		}

		$query = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'title'          => $product_name,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! $query->have_posts() ) {
			wp_send_json_error( array( 'message' => 'Product not found: ' . $product_name ) );
			return;
		}

		$product_id = (int) $query->posts[0];
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, (int) $_POST['quantity'] ) : 1;

		if ( is_null( WC()->session ) || ! WC()->session->has_session() ) {
			WC()->initialize_session();
			WC()->session->set_customer_session_cookie( true );
		}

		if ( is_null( WC()->cart ) ) {
			WC()->initialize_cart();
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );
		if ( false === $cart_item_key ) {
			wp_send_json_error(
				array(
					'message'    => 'Failed to add product to cart',
					'product_id' => $product_id,
				)
			);
			return;
		}

		wp_send_json_success(
			array(
				'message'       => 'Product added to cart',
				'product_id'    => $product_id,
				'cart_item_key' => $cart_item_key,
				'cart_count'    => WC()->cart->get_cart_contents_count(),
			)
		);
	}
}
