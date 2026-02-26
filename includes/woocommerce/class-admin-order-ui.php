<?php
/**
 * Adds a metabox to the WooCommerce admin order screen showing Venmo payment information.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

namespace BrianHenryIE\WC_Venmo_Gateway\WooCommerce;

use BrianHenryIE\WC_Venmo_Gateway\chillerlan\QRCode\QRCode;
use BrianHenryIE\WC_Venmo_Gateway\chillerlan\QRCode\QROptions;
use WC_Order;
use WC_Payment_Gateways;
use WP_Post;

/**
 * Displays a Venmo payment metabox on the WooCommerce admin order screen.
 */
class Admin_Order_UI {

	/**
	 * Registers the metabox on both the legacy (shop_order post) and HPOS (woocommerce_page_wc-orders) screens.
	 *
	 * @hooked add_meta_boxes
	 */
	public function add_venmo_payment_metabox(): void {
		foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_meta_box(
				'bh-wc-venmo-payment',
				__( 'Venmo Payment', 'bh-wc-venmo-gateway' ),
				array( $this, 'render_venmo_payment_metabox' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Renders the metabox content.
	 *
	 * @param WC_Order|WP_Post $order_or_post The order object (HPOS) or post (legacy).
	 */
	public function render_venmo_payment_metabox( WC_Order|WP_Post $order_or_post ): void {

		$order = $order_or_post instanceof WC_Order
			? $order_or_post
			: wc_get_order( $order_or_post->ID );

		if ( ! ( $order instanceof WC_Order ) ) {
			return;
		}

		$payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

		if ( ! isset( $payment_gateways[ $order->get_payment_method() ] ) ) {
			return;
		}

		$payment_gateway_instance = $payment_gateways[ $order->get_payment_method() ];

		if ( ! ( $payment_gateway_instance instanceof Venmo_Gateway ) ) {
			return;
		}

		$customer_venmo_username = $order->get_meta( Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY );
		$store_venmo_username    = $order->get_meta( Venmo_Gateway::STORE_VENMO_USERNAME_META_KEY );

		if ( empty( $store_venmo_username ) ) {
			echo '<p>' . esc_html__( 'No store Venmo username recorded for this order.', 'bh-wc-venmo-gateway' ) . '</p>';
			return;
		}

		$order_id = $order->get_id();

		/**
		 * Template: `https://venmo.com/{username}?txn=pay&amount={amount}&note={note}`.
		 */
		$venmo_payment_url = sprintf(
			'https://venmo.com/%s?txn=pay&amount=%s&note=%s',
			rawurlencode( $store_venmo_username ),
			rawurlencode( $order->get_total() ),
			rawurlencode( 'order ' . $order_id )
		);

		$venmo_payment_url_display = sprintf(
			'<span>https://venmo.com/</span><span>%s?</span><span>txn=pay&</span><span>amount=%s&</span><span>note=%s</span>',
			rawurlencode( $store_venmo_username ),
			rawurlencode( $order->get_total() ),
			rawurlencode( 'order ' . $order_id )
		);

		$qr_options          = new class() extends QROptions {
			protected int $quietzoneSize = 1;
		};
		$qr_code_data_base64 = ( new QRCode( $qr_options ) )->render( $venmo_payment_url );
		$venmo_image_url     = plugins_url( 'assets/woocommerce/images/venmo-logo-25.png', 'bh-wc-venmo-gateway/bh-wc-venmo-gateway.php' );
		$order_total         = "\${$order->get_total()}";

		?>
		<div style="text-align: center;">

			<?php if ( ! empty( $customer_venmo_username ) ) : ?>
				<p>
					Customer:
					<a href="<?php echo esc_url( 'https://venmo.com/u/' . $customer_venmo_username ); ?>">
					<?php
					printf(
						/* translators: %s: customer Venmo username */
						esc_html__( '@%s', 'bh-wc-venmo-gateway' ),
						esc_html( $customer_venmo_username )
					);
					?>
					</a>
				</p>
			<?php endif; ?>

			<p>
				<?php
				printf(
					wp_kses(
						/* translators: 1: formatted order total, 2: store Venmo payment URL, 3: store Venmo username */
						__( 'Send <strong>%1$s</strong> to <a href="%2$s">@%3$s</a>', 'bh-wc-venmo-gateway' ),
						array(
							'strong' => array(),
							'a'      => array( 'href' => array() ),
						)
					),
					esc_html( $order_total ),
					esc_url( $venmo_payment_url ),
					esc_html( $store_venmo_username )
				);
				?>
			</p>

			<p>
				<a href="<?php echo esc_url( $venmo_payment_url ); ?>">
					<img src="<?php echo esc_url( $venmo_image_url ); ?>" alt="Venmo" />
				</a>
			</p>

			<p>
				<a href="<?php echo esc_url( $venmo_payment_url ); ?>">
					<img
						src="<?php echo esc_attr( $qr_code_data_base64 ); ?>"
						alt="<?php esc_attr_e( 'Venmo payment QR code', 'bh-wc-venmo-gateway' ); ?>"
						style="max-width: 100%; height: auto;"
					/>
				</a>
			</p>

			<p>
				<a href="<?php echo esc_url( $venmo_payment_url ); ?>">
					<?php
					echo $venmo_payment_url_display;
					?>
				</a>
			</p>

		</div>
		<?php
	}
}
