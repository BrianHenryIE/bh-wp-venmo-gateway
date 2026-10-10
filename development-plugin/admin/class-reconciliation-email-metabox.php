<?php
/**
 * Adds a "Send reconciliation email" button to the admin order screen which creates a Venmo
 * payment email for the order in the plugin's mailbox.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Admin;

use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\API\Venmo_Payment_Email;
use Throwable;
use WC_Order;
use WP_Post;

/**
 * Metabox + admin-post handler + admin notice.
 */
class Reconciliation_Email_Metabox {

	const ACTION = 'juiced_venmo_gateway_pro_send_reconciliation_email';

	/**
	 * Query arg carrying the result back to the order screen after the redirect.
	 */
	const RESULT_QUERY_ARG = 'juiced_venmo_gateway_pro_reconciliation_email';

	/**
	 * Transient key prefix for the error message, keyed by user id.
	 */
	const ERROR_TRANSIENT_PREFIX = 'juiced_venmo_gateway_pro_reconciliation_email_error_';

	/**
	 * Add the metabox, the form handler, and the result notice.
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_send' ) );
		add_action( 'admin_notices', array( $this, 'print_result_notice' ) );
	}

	/**
	 * Register the metabox on both the legacy (shop_order post) and HPOS (woocommerce_page_wc-orders) screens.
	 *
	 * @hooked add_meta_boxes
	 * @see do_meta_boxes()
	 */
	public function add_metabox(): void {
		foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_meta_box(
				'juiced-venmo-gateway-pro-development-reconciliation-email',
				'Venmo Development',
				array( $this, 'render_metabox' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Print the button.
	 *
	 * @param WC_Order|WP_Post $order_or_post The order object (HPOS) or post (legacy).
	 */
	public function render_metabox( WC_Order|WP_Post $order_or_post ): void {
		$order = $order_or_post instanceof WC_Order
			? $order_or_post
			: wc_get_order( $order_or_post->ID );

		if ( ! ( $order instanceof WC_Order ) ) {
			return;
		}

		// The metabox is rendered inside WooCommerce's order form, so a nested form is not possible; use a nonce-protected link.
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::ACTION,
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $order->get_id()
		);

		echo '<p>Creates a Venmo "paid you" email in the mailbox for this order\'s customer name and total, and runs the email reconciliation.</p>';
		echo '<p><a class="button button-secondary" id="juiced-venmo-gateway-pro-send-reconciliation-email" href="' . esc_url( $url ) . '">Send reconciliation email</a></p>';
	}

	/**
	 * Create the email, then redirect back to the order screen with the result.
	 *
	 * Reached by the link in the metabox: `admin-post.php?action=...&order_id=...&_wpnonce=...`.
	 *
	 * @hooked admin_post_juiced_venmo_gateway_pro_send_reconciliation_email
	 * @see wp-admin/admin-post.php
	 */
	public function handle_send(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified on the next line; the order id is part of the nonce action.
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;

		// phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce meta capability for this order.
		if ( ! current_user_can( 'edit_shop_order', $order_id ) ) {
			wp_die( 'You are not allowed to do that.', '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION . '_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! ( $order instanceof WC_Order ) ) {
			wp_die( 'Order not found.', '', array( 'response' => 404 ) );
		}

		try {
			$created_email = ( new Venmo_Payment_Email() )->create_for_order( $order );

			$order->add_order_note(
				sprintf(
					'Development plugin: created Venmo reconciliation email <a href="%s">post #%d, "%s"</a>.',
					esc_url( $this->get_email_edit_url( $created_email->post_id ) ),
					$created_email->post_id,
					esc_html( $created_email->subject )
				)
			);

			$result = (string) $created_email->post_id;
		} catch ( Throwable $exception ) {
			set_transient( self::ERROR_TRANSIENT_PREFIX . get_current_user_id(), $exception->getMessage(), MINUTE_IN_SECONDS );
			$result = 'error';
		}

		wp_safe_redirect( add_query_arg( self::RESULT_QUERY_ARG, $result, $order->get_edit_order_url() ) );
		exit;
	}

	/**
	 * Show the outcome of the last send on the order screen.
	 *
	 * @hooked admin_notices
	 * @see wp-admin/admin-header.php
	 */
	public function print_result_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, set by our own redirect.
		if ( ! isset( $_GET[ self::RESULT_QUERY_ARG ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = sanitize_text_field( wp_unslash( $_GET[ self::RESULT_QUERY_ARG ] ) );

		if ( 'error' === $result ) {
			$message = get_transient( self::ERROR_TRANSIENT_PREFIX . get_current_user_id() );
			delete_transient( self::ERROR_TRANSIENT_PREFIX . get_current_user_id() );
			printf(
				'<div class="notice notice-error is-dismissible" id="juiced-venmo-gateway-pro-reconciliation-email-notice"><p>Failed to create the reconciliation email: %s</p></div>',
				esc_html( is_string( $message ) ? $message : 'unknown error' )
			);
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible" id="juiced-venmo-gateway-pro-reconciliation-email-notice"><p>Created Venmo reconciliation email <a href="%s">post #%d</a> and ran reconciliation. Reload to see any status change.</p></div>',
			esc_url( $this->get_email_edit_url( absint( $result ) ) ),
			absint( $result )
		);
	}

	/**
	 * The email's single view: the emails post type's edit screen, where bh-wp-mailboxes adds its metaboxes.
	 *
	 * @see \JuicedPlugins\Venmo_Gateway_Pro\WP_Mailboxes\Admin\Single_Email_View::add_meta_boxes()
	 *
	 * @param int $post_id The email's wp_posts id.
	 */
	protected function get_email_edit_url( int $post_id ): string {
		return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	}
}
