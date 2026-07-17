<?php
/**
 * Show the Venmo payment QR code on the GiveWP donation confirmation page.
 *
 * A Venmo donation is created `pending` and awaits an off-platform payment, so
 * the confirmation page (`/donation-confirmation/`, where GiveWP prints
 * "Payment Pending: Your donation is currently processing.") shows the QR code
 * the donor scans to pay.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\chillerlan\QRCode\Output\QROutputInterface;
use BrianHenryIE\WP_Venmo_Gateway\QR\QR_Code;
use BrianHenryIE\WP_Venmo_Gateway\Venmo_Username;
use Give\Donations\Models\Donation;
use Give\Framework\Receipts\DonationReceipt;
use Give\Framework\Receipts\Properties\ReceiptDetail;

/**
 * Adds the Venmo payment QR code to the GiveWP donation confirmation receipt.
 */
class Donation_Receipt {

	/**
	 * Replace GiveWP's generic "…is currently processing." pending notice with a
	 * Venmo-specific call to action telling the donor exactly how much to send and
	 * to which @username.
	 *
	 * @hooked give_receipt_status_notice
	 * @see /wp-content/plugins/give/templates/shortcode-receipt.php
	 *
	 * @param string     $notice      The rendered notice HTML.
	 * @param int|string $id          The page/post ID the notice is displayed on.
	 * @param string     $status      The donation status.
	 * @param int|string $donation_id The donation ID.
	 */
	public function customize_pending_notice( string $notice, $id, string $status, $donation_id ): string {

		$donation_id = (int) $donation_id;

		if ( 'pending' !== $status || empty( $donation_id ) ) {
			return $notice;
		}

		if ( Venmo_Gateway::id() !== give_get_payment_gateway( $donation_id ) ) {
			return $notice;
		}

		$store_username = give_get_meta( $donation_id, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, true );

		if ( empty( $store_username ) ) {
			return $notice;
		}

		$amount = (string) give_donation_amount( $donation_id );

		// Render the notice through GiveWP (so it keeps GiveWP's markup and styling),
		// then swap a placeholder for the payment hyperlink. print_frontend_notice()
		// runs esc_html() on the message, so the link cannot be passed through it
		// directly; the placeholder survives escaping unchanged.
		$placeholder = '{{venmo_payment_link}}';

		$message = sprintf(
			/* translators: %s: the linked "$25 via Venmo to @username" call to action */
			__( 'Payment Pending: Please send your donation of %s.', 'bh-wp-venmo-gateway' ),
			$placeholder
		);

		$notice = \Give_Notices::print_frontend_notice( $message, false, 'warning' );

		$link_text = sprintf(
			/* translators: 1: donation amount, 2: store Venmo username */
			__( '$%1$s via Venmo to @%2$s', 'bh-wp-venmo-gateway' ),
			$amount,
			$store_username
		);

		$link = sprintf(
			'<a target="_blank" href="%s">%s</a>',
			esc_url( $this->get_browser_url( $store_username, $amount, $donation_id ) ),
			esc_html( $link_text )
		);

		return str_replace( $placeholder, $link, $notice );
	}

	/**
	 * Replace the v3 (Sequoia) confirmation receipt heading with the Venmo payment
	 * QR code, and add a "Payment Pending" detail linking to the Venmo payment.
	 *
	 * While the donation is pending, the celebratory "Hey {name}, thanks for your
	 * donation!" heading is misleading, so it is replaced with the QR code the donor
	 * scans to pay. The v3 receipt is a React app fed server-side data: the heading
	 * and detail values are rendered through Interweave, so the QR `<img>` and the
	 * payment link are parsed as HTML. This hook runs after the heading is set, so
	 * overwriting it here takes effect.
	 *
	 * @hooked givewp_generate_confirmation_page_receipt_before_donation_total
	 * @see \Give\Framework\Receipts\Actions\GenerateConfirmationPageReceipt
	 *
	 * @param DonationReceipt $receipt The receipt being built (modified in place).
	 */
	public function add_v3_receipt_details( DonationReceipt $receipt ): void {

		$donation = $receipt->donation;

		if ( Venmo_Gateway::id() !== $donation->gatewayId ) {
			return;
		}

		// Only prompt for payment while the donation is awaiting it.
		if ( ! $donation->status->isPending() ) {
			return;
		}

		$store_username = give_get_meta( $donation->id, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, true );

		if ( empty( $store_username ) ) {
			$store_username = give_get_option( 'venmo_store_username', '' );
		}

		if ( empty( $store_username ) ) {
			return;
		}

		$amount      = (string) give_donation_amount( $donation->id );
		$browser_url = $this->get_browser_url( $store_username, $amount, $donation->id );

		// Replace the heading with the QR code. PNG (not the SVG default): the v3
		// receipt renders content through Interweave, which strips the `style`
		// attribute. A raster PNG carries its own intrinsic dimensions, so the QR is
		// visible without inline CSS; an SVG without width/height would collapse to 0×0.
		$qr_code_data_uri = ( new QR_Code() )->get_data_uri(
			$this->get_qr_deep_link( $store_username, $amount, $donation->id ),
			QROutputInterface::GDIMAGE_PNG
		);

		$qr_html = sprintf(
			'<a target="_blank" href="%1$s"><img src="%2$s" alt="%3$s" /></a>',
			esc_url( $browser_url ),
			esc_attr( $qr_code_data_uri ),
			esc_attr__( 'Payment QR code', 'bh-wp-venmo-gateway' )
		);

		$receipt->settings->addSetting( 'heading', $qr_html );

		$link = sprintf(
			'<a target="_blank" href="%s">%s</a>',
			esc_url( $browser_url ),
			esc_html(
				sprintf(
					/* translators: 1: donation amount, 2: store Venmo username */
					__( '$%1$s via Venmo to @%2$s', 'bh-wp-venmo-gateway' ),
					$amount,
					$store_username
				)
			)
		);

		$receipt->donationDetails->addDetail(
			new ReceiptDetail(
				__( 'Pending', 'bh-wp-venmo-gateway' ),
				$link
			)
		);
	}

	/**
	 * Replace the v3 receipt's "Success!" badge with a Venmo payment link while the
	 * donation is pending — a pending donation has not succeeded yet.
	 *
	 * "Success!" is a static string in GiveWP's React receipt component and cannot
	 * be changed server-side, so a small script is printed on the receipt iframe
	 * page that swaps the badge content once the React app has mounted.
	 *
	 * @hooked givewp_donation_confirmation_receipt_showing
	 * @see \Give\DonationForms\Controllers\DonationConfirmationReceiptViewController::show()
	 *
	 * @param Donation $donation The donation whose receipt is being shown.
	 */
	public function replace_v3_success_badge( Donation $donation ): void {

		if ( Venmo_Gateway::id() !== $donation->gatewayId ) {
			return;
		}

		if ( ! $donation->status->isPending() ) {
			return;
		}

		$store_username = give_get_meta( $donation->id, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, true );

		if ( empty( $store_username ) ) {
			$store_username = give_get_option( 'venmo_store_username', '' );
		}

		if ( empty( $store_username ) ) {
			return;
		}

		$amount = (string) give_donation_amount( $donation->id );

		$data = array(
			'url'   => $this->get_browser_url( $store_username, $amount, $donation->id ),
			'label' => sprintf(
				/* translators: 1: donation amount, 2: store Venmo username */
				__( 'Please pay $%1$s via Venmo to @%2$s', 'bh-wp-venmo-gateway' ),
				$amount,
				$store_username
			),
		);

		$handle = 'bh-wp-venmo-gateway-givewp-receipt';
		wp_register_script( $handle, false, array(), BH_WP_VENMO_GATEWAY_VERSION, true );
		wp_enqueue_script( $handle );
		wp_add_inline_script(
			$handle,
			'window.bhWpVenmoReceipt = ' . wp_json_encode( $data ) . ';' . $this->get_badge_replacement_js()
		);
	}

	/**
	 * The client-side script that swaps the receipt's "Success!" badge for the
	 * Venmo payment link. Re-applies on DOM changes so it survives the React app
	 * mounting (and any re-render) within the first few seconds.
	 */
	private function get_badge_replacement_js(): string {
		return <<<'JS'
			( function () {
				var data = window.bhWpVenmoReceipt || {};
				function apply() {
					var badge = document.querySelector( '.givewp-form-secure-badge' );
					if ( ! badge || badge.querySelector( 'a[data-bh-venmo]' ) ) {
						return;
					}
					badge.textContent = '';
					var link = document.createElement( 'a' );
					link.setAttribute( 'data-bh-venmo', '1' );
					link.setAttribute( 'target', '_blank' );
					link.href = data.url;
					link.textContent = data.label;
					badge.appendChild( link );
				}
				apply();
				var observer = new MutationObserver( apply );
				observer.observe( document.documentElement, { childList: true, subtree: true } );
				setTimeout( function () { observer.disconnect(); }, 15000 );
			} )();
			JS;
	}

	/**
	 * The `https://venmo.com/…` payment URL for desktop browsers.
	 *
	 * @param string $store_username The destination Venmo @username.
	 * @param string $amount         The donation amount.
	 * @param int    $donation_id    The donation ID (used as the payment note).
	 */
	private function get_browser_url( string $store_username, string $amount, int $donation_id ): string {
		return sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( Venmo_Username::sanitize( $store_username ) ),
			rawurlencode( $amount ),
			rawurlencode( 'donation ' . $donation_id )
		);
	}

	/**
	 * The `venmo://` deep link encoded in the QR code (opens the Venmo app on a phone).
	 *
	 * @param string $store_username The destination Venmo @username.
	 * @param string $amount         The donation amount.
	 * @param int    $donation_id    The donation ID (used as the payment note).
	 */
	private function get_qr_deep_link( string $store_username, string $amount, int $donation_id ): string {
		return sprintf(
			'venmo://paycharge?txn=pay&recipients=%s&note=%s&amount=%s',
			Venmo_Username::sanitize( $store_username ),
			rawurlencode( 'donation ' . $donation_id ),
			rawurlencode( $amount )
		);
	}

	/**
	 * Print the Venmo payment QR code and instructions above the receipt table.
	 *
	 * Only renders for `pending` Venmo donations that have a destination username.
	 *
	 * @hooked give_payment_receipt_before_table
	 * @see /wp-content/plugins/give/templates/shortcode-receipt.php
	 *
	 * @param object               $donation          The donation post object (has `->ID`).
	 * @param array<string, mixed> $give_receipt_args The receipt shortcode arguments.
	 */
	public function print_qr_code( object $donation, array $give_receipt_args ): void {

		$donation_id = isset( $donation->ID ) ? (int) $donation->ID : 0;

		if ( empty( $donation_id ) ) {
			return;
		}

		if ( Venmo_Gateway::id() !== give_get_payment_gateway( $donation_id ) ) {
			return;
		}

		// Only prompt for payment while the donation is awaiting it.
		if ( 'pending' !== get_post_status( $donation_id ) ) {
			return;
		}

		$store_username = give_get_meta( $donation_id, Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY, true );

		if ( empty( $store_username ) ) {
			return;
		}

		$amount = (string) give_donation_amount( $donation_id );

		$venmo_browser_url = $this->get_browser_url( $store_username, $amount, $donation_id );
		$qr_code_data_uri  = ( new QR_Code() )->get_data_uri( $this->get_qr_deep_link( $store_username, $amount, $donation_id ) );

		?>
		<div class="bh-wp-venmo-gateway-donation-instructions" style="text-align: center;">

			<p>
				<a target="_blank" href="<?php echo esc_url( $venmo_browser_url ); ?>">
					<img
						style="display:block; margin: 0 auto; max-width: 90vw; max-height: 500px;"
						src="<?php echo esc_attr( $qr_code_data_uri ); ?>"
						alt="<?php esc_attr_e( 'Payment QR code', 'bh-wp-venmo-gateway' ); ?>"
					/>
				</a>
			</p>

		</div>
		<?php
	}
}
