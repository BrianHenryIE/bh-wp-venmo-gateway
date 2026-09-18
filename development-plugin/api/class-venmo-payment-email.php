<?php
/**
 * Builds a realistic Venmo "paid you" email for an order and stores it in the plugin's mailbox,
 * as though it had been received via the REST ingress endpoint.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\API;

use BrianHenryIE\WP_Venmo_Gateway\API\Settings;
use Exception;
use WC_Order;
use WP_REST_Request;

/**
 * Uses `tests/_data/John Doe paid you $46.00.eml` as a template, substituting the order's customer name,
 * total and order number, then POSTs it to the mailbox library's REST ingress endpoint, exactly as the
 * Cloudflare Email Routing worker does for real emails.
 *
 * @see \BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\Connections\Rest\REST_Ingress_Connection::create_new_email()
 */
class Venmo_Payment_Email {

	/**
	 * Values in the template email that are replaced.
	 */
	const TEMPLATE_CUSTOMER_NAME  = 'John Doe';
	const TEMPLATE_AMOUNT         = '46.00';
	const TEMPLATE_NOTE           = 'None of your damn biz';
	const TEMPLATE_TRANSACTION_ID = '4673461554100496023';

	/**
	 * The template email, relative to the main plugin's directory.
	 */
	const TEMPLATE_PATH = 'tests/_data/John Doe paid you $46.00.eml';

	/**
	 * The plugin settings, which provide the REST namespace and emails CPT name the ingress route is built from.
	 */
	protected Settings $settings;

	/**
	 * Uses the plugin's own settings so the route matches the plugin's registered mailbox.
	 */
	public function __construct() {
		$this->settings = new Settings();
	}

	/**
	 * Create a Venmo payment email matching the order and deliver it to the REST ingress endpoint.
	 *
	 * The request is dispatched internally as the current user, whose `edit_posts` capability satisfies
	 * the endpoint's permission callback.
	 *
	 * @param WC_Order $order The order the email should appear to be payment for.
	 *
	 * @return Created_Email The stored email's post id and subject.
	 *
	 * @throws Exception When the template cannot be read or the endpoint rejects the email.
	 */
	public function create_for_order( WC_Order $order ): Created_Email {

		$raw_mime = $this->build_mime_for_order( $order );

		$route = sprintf(
			'/%s/v2/%s/new',
			$this->settings->get_rest_namespace(),
			$this->settings->get_emails_cpt_dashed()
		);

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'message/rfc822' );
		$request->set_body( $raw_mime );

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			$error = $response->as_error();
			throw new Exception( sprintf( 'REST ingress returned %d: %s', $response->get_status(), $error ? $error->get_error_message() : 'unknown error' ) );
		}

		/**
		 * The endpoint's success response.
		 *
		 * @var array{post_id:int, message_id:string} $data
		 */
		$data = $response->get_data();

		return new Created_Email( $data['post_id'], $this->get_subject_for_order( $order ) );
	}

	/**
	 * The email subject, e.g. "John Doe paid you $46.00".
	 *
	 * @param WC_Order $order The order whose billing name and total are used.
	 */
	protected function get_subject_for_order( WC_Order $order ): string {
		return sprintf( '%s paid you $%s', $this->get_customer_name( $order ), $this->get_amount( $order ) );
	}

	/**
	 * The order's billing name, falling back to the template's name when the order has none.
	 *
	 * @param WC_Order $order The order.
	 */
	protected function get_customer_name( WC_Order $order ): string {
		$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		return '' === $customer_name ? self::TEMPLATE_CUSTOMER_NAME : $customer_name;
	}

	/**
	 * The order total formatted as Venmo displays it, e.g. "46.00".
	 *
	 * @param WC_Order $order The order.
	 */
	protected function get_amount( WC_Order $order ): string {
		return number_format( (float) $order->get_total(), 2, '.', '' );
	}

	/**
	 * Read the template email and substitute the order's details.
	 *
	 * The HTML part is quoted-printable encoded with soft line breaks, so it is decoded before
	 * the substitutions are made and re-encoded afterwards.
	 *
	 * @param WC_Order $order The order whose customer name, total and id are used.
	 *
	 * @throws Exception When the template file cannot be read.
	 */
	public function build_mime_for_order( WC_Order $order ): string {

		$template_path = WP_PLUGIN_DIR . '/' . dirname( constant( 'BH_WP_VENMO_GATEWAY_BASENAME' ) ) . '/' . self::TEMPLATE_PATH;

		$raw_mime = file_get_contents( $template_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw_mime ) {
			throw new Exception( "Could not read template email at {$template_path}." );
		}
		$raw_mime = str_replace( "\r\n", "\n", $raw_mime );

		$customer_name  = $this->get_customer_name( $order );
		$amount         = $this->get_amount( $order );
		$note           = sprintf( 'Order #%d', $order->get_id() );
		$transaction_id = (string) random_int( 100000000000000000, 999999999999999999 );

		list( $headers, $body ) = explode( "\n\n", $raw_mime, 2 );

		// `$` and `\` in the replacement are escaped so "$18.00" is not read as a backreference.
		$headers = (string) preg_replace( '/^Subject: .*$/m', addcslashes( 'Subject: ' . $this->get_subject_for_order( $order ), '\\$' ), $headers );
		$headers = (string) preg_replace( '/^Message-ID: .*$/m', sprintf( 'Message-ID: <development-plugin-%d-%s@%s>', $order->get_id(), uniqid(), (string) wp_parse_url( site_url(), PHP_URL_HOST ) ), $headers );
		$headers = (string) preg_replace( '/^Date: .*$/m', 'Date: ' . gmdate( 'D, d M Y H:i:s +0000' ), $headers );

		if ( 1 !== preg_match( '/boundary="([^"]+)"/', $headers, $matches ) ) {
			throw new Exception( 'Could not find the MIME boundary in the template email.' );
		}
		$boundary = $matches[1];

		$parts = explode( "--{$boundary}", $body );
		foreach ( $parts as $index => $part ) {
			if ( false === strpos( $part, 'Content-Type: text/html' ) ) {
				continue;
			}
			list( $part_headers, $part_body ) = explode( "\n\n", $part, 2 );

			$html = quoted_printable_decode( $part_body );
			$html = $this->substitute_html( $html, $customer_name, $amount, $note, $transaction_id );

			$parts[ $index ] = $part_headers . "\n\n" . quoted_printable_encode( $html ) . "\n";
		}

		return $headers . "\n\n" . implode( "--{$boundary}", $parts );
	}

	/**
	 * Replace the template's customer name, amount, note and transaction id in the decoded HTML body.
	 *
	 * The amount is displayed both inline ("$46.00") and split into dollars and cents in separate elements.
	 *
	 * @param string $html           The decoded HTML body of the template email.
	 * @param string $customer_name  The order's billing name.
	 * @param string $amount         The order total, e.g. "46.00".
	 * @param string $note           The payment note, e.g. "Order #123".
	 * @param string $transaction_id A random Venmo-style transaction id.
	 */
	protected function substitute_html( string $html, string $customer_name, string $amount, string $note, string $transaction_id ): string {
		list( $dollars, $cents ) = explode( '.', $amount );

		$html = str_replace( self::TEMPLATE_CUSTOMER_NAME, $customer_name, $html );
		$html = str_replace( '$' . self::TEMPLATE_AMOUNT, '$' . $amount, $html );
		$html = str_replace( 'line-height:40px">46</div>', 'line-height:40px">' . $dollars . '</div>', $html );
		$html = (string) preg_replace( '/(padding-top:1px">)00(<\/div>)/', '${1}' . $cents . '$2', $html, 1 );
		$html = str_replace( self::TEMPLATE_NOTE, $note, $html );
		$html = str_replace( self::TEMPLATE_TRANSACTION_ID, $transaction_id, $html );

		return $html;
	}
}
