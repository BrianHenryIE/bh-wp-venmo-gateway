<?php
/**
 * Adds a "Mark paid" action to pending Venmo donations on the legacy
 * donations list table, opening a modal to record the payment details
 * (Venmo username, transaction id, payment date) before completing them.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

/**
 * Adds the "Mark paid" modal workflow to the legacy donations list table.
 */
class Donations_List {

	const AJAX_ACTION  = 'bh_venmo_mark_paid';
	const NONCE_ACTION = 'bh_venmo_mark_paid';

	/**
	 * Append a "Mark paid" link beneath the status of pending Venmo donations
	 * in the Status column of the donations list table.
	 *
	 * @hooked give_payments_table_column
	 * @see \Give_Payment_History_Table::column_default()
	 *
	 * @param string $value       The rendered column HTML.
	 * @param int    $payment_id  The donation (payment) id for the row.
	 * @param string $column_name The column being rendered.
	 */
	public function add_mark_paid_link( string $value, int $payment_id, string $column_name ): string {
		if ( 'status' !== $column_name ) {
			return $value;
		}

		if ( Venmo_Gateway::id() !== give_get_payment_gateway( $payment_id ) ) {
			return $value;
		}

		if ( 'pending' !== give_get_payment_status( $payment_id ) ) {
			return $value;
		}

		if ( ! current_user_can( 'edit_give_payments' ) ) {
			return $value;
		}

		$link = sprintf(
			'<div class="row-actions"><span class="bh-venmo-mark-paid-wrap"><a href="#" class="bh-venmo-mark-paid" data-donation-id="%1$d">%2$s</a></span></div>',
			$payment_id,
			esc_html__( 'Mark paid', 'bh-wp-venmo-gateway' )
		);

		return $value . $link;
	}

	/**
	 * Enqueue the modal script and styles on the donations list page only.
	 *
	 * @hooked admin_enqueue_scripts
	 * @see \do_action( 'admin_enqueue_scripts' )
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_donations_list_page() ) {
			return;
		}

		wp_enqueue_style(
			'bh-wp-venmo-gateway-donations-list',
			plugins_url( 'assets/givewp/donations-list.css', BH_WP_VENMO_GATEWAY_FILE ),
			array(),
			BH_WP_VENMO_GATEWAY_VERSION
		);

		wp_enqueue_script(
			'bh-wp-venmo-gateway-donations-list',
			plugins_url( 'assets/givewp/donations-list.js', BH_WP_VENMO_GATEWAY_FILE ),
			array(),
			BH_WP_VENMO_GATEWAY_VERSION,
			true
		);

		wp_localize_script(
			'bh-wp-venmo-gateway-donations-list',
			'bhVenmoMarkPaid',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Print the (hidden) modal markup once in the admin footer of the list page.
	 *
	 * @hooked admin_footer
	 * @see \do_action( 'admin_footer' )
	 */
	public function render_modal(): void {
		if ( ! $this->is_donations_list_page() ) {
			return;
		}

		$today = current_time( 'Y-m-d' );
		?>
		<div id="bh-venmo-mark-paid-modal" class="bh-venmo-modal" hidden>
			<div class="bh-venmo-modal__overlay" data-bh-venmo-close></div>
			<div class="bh-venmo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="bh-venmo-modal-title">
				<h2 id="bh-venmo-modal-title"><?php esc_html_e( 'Mark Venmo donation paid', 'bh-wp-venmo-gateway' ); ?></h2>
				<form id="bh-venmo-mark-paid-form">
					<input type="hidden" name="donation_id" value="">
					<p>
						<label for="bh-venmo-username"><?php esc_html_e( 'Venmo @username paid with', 'bh-wp-venmo-gateway' ); ?></label>
						<input type="text" id="bh-venmo-username" name="venmo_username" placeholder="@username">
					</p>
					<p>
						<label for="bh-venmo-transaction-id"><?php esc_html_e( 'Transaction ID', 'bh-wp-venmo-gateway' ); ?></label>
						<input type="text" id="bh-venmo-transaction-id" name="transaction_id">
					</p>
					<p>
						<label for="bh-venmo-payment-date"><?php esc_html_e( 'Payment date', 'bh-wp-venmo-gateway' ); ?></label>
						<input type="date" id="bh-venmo-payment-date" name="payment_date" value="<?php echo esc_attr( $today ); ?>">
					</p>
					<p>
						<label for="bh-venmo-payment-time"><?php esc_html_e( 'Payment time', 'bh-wp-venmo-gateway' ); ?></label>
						<input type="time" id="bh-venmo-payment-time" name="payment_time">
					</p>
					<p class="bh-venmo-modal__hint"><?php esc_html_e( 'All fields are optional.', 'bh-wp-venmo-gateway' ); ?></p>
					<p class="bh-venmo-modal__error" role="alert" hidden></p>
					<div class="bh-venmo-modal__actions">
						<button type="button" class="button" data-bh-venmo-close><?php esc_html_e( 'Cancel', 'bh-wp-venmo-gateway' ); ?></button>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Mark paid', 'bh-wp-venmo-gateway' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Record the (optional) payment details and mark the donation complete.
	 *
	 * @hooked wp_ajax_bh_venmo_mark_paid
	 * @see \do_action( 'wp_ajax_bh_venmo_mark_paid' )
	 */
	public function ajax_mark_paid(): void {
		check_ajax_referer( self::NONCE_ACTION, 'security' );

		if ( ! current_user_can( 'edit_give_payments' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to update donations.', 'bh-wp-venmo-gateway' ) ),
				403
			);
		}

		$donation_id = isset( $_POST['donation_id'] ) ? absint( wp_unslash( $_POST['donation_id'] ) ) : 0;

		if ( 0 === $donation_id || Venmo_Gateway::id() !== give_get_payment_gateway( $donation_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Donation not found.', 'bh-wp-venmo-gateway' ) ),
				404
			);
		}

		$venmo_username = isset( $_POST['venmo_username'] ) ? sanitize_text_field( wp_unslash( $_POST['venmo_username'] ) ) : '';
		$transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
		$payment_date   = isset( $_POST['payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_date'] ) ) : '';
		$payment_time   = isset( $_POST['payment_time'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_time'] ) ) : '';

		// The time is optional; when given it is appended to the date (e.g. "2026-07-08 14:30").
		$payment_datetime = trim( $payment_date . ' ' . $payment_time );

		if ( '' !== $venmo_username ) {
			give_update_meta( $donation_id, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, ltrim( $venmo_username, '@' ) );
		}
		if ( '' !== $transaction_id ) {
			give_update_meta( $donation_id, Venmo_Gateway::VENMO_TRANSACTION_ID_META_KEY, $transaction_id );
		}
		if ( '' !== $payment_datetime ) {
			give_update_meta( $donation_id, Venmo_Gateway::VENMO_PAYMENT_DATE_META_KEY, $payment_datetime );
		}

		give_insert_payment_note( $donation_id, $this->build_note( $venmo_username, $transaction_id, $payment_datetime ) );

		give_update_payment_status( $donation_id, 'publish' );

		wp_send_json_success(
			array(
				'donationId' => $donation_id,
				'status'     => give_get_payment_status( $donation_id, true ),
			)
		);
	}

	/**
	 * Compose the donation note recording how the payment was confirmed.
	 *
	 * @param string $venmo_username   The Venmo @username the donor paid with.
	 * @param string $transaction_id   The Venmo transaction id.
	 * @param string $payment_datetime The date (and optional time) the payment was made.
	 */
	private function build_note( string $venmo_username, string $transaction_id, string $payment_datetime ): string {
		$note = __( 'Marked paid via Venmo.', 'bh-wp-venmo-gateway' );

		if ( '' !== $venmo_username ) {
			/* translators: %s: Venmo @username */
			$note .= ' ' . sprintf( __( 'Paid by @%s.', 'bh-wp-venmo-gateway' ), ltrim( $venmo_username, '@' ) );
		}
		if ( '' !== $transaction_id ) {
			/* translators: %s: Venmo transaction id */
			$note .= ' ' . sprintf( __( 'Transaction ID: %s.', 'bh-wp-venmo-gateway' ), $transaction_id );
		}
		if ( '' !== $payment_datetime ) {
			/* translators: %s: payment date (and optional time) */
			$note .= ' ' . sprintf( __( 'Payment date: %s.', 'bh-wp-venmo-gateway' ), $payment_datetime );
		}

		return $note;
	}

	/**
	 * Whether the current admin request is the legacy donations list page.
	 */
	private function is_donations_list_page(): bool {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a page identifier, not processing input.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return 'edit.php' === $pagenow && 'give-payment-history' === $page;
	}
}
