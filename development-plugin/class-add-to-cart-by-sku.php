<?php
/**
 * `?add-to-cart-sku=woo-beanie` adds a product to the cart by its SKU, then redirects to the checkout.
 *
 * WooCommerce's own `?add-to-cart=` takes a product ID, which is not known in advance when the sample products are
 * imported, e.g. in the WordPress Playground preview, whose landing page uses this.
 *
 * @see .github/workflows/playground-preview.yml
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin;

use WC_Form_Handler;

/**
 * Translates the SKU to a product ID for WooCommerce's `add-to-cart` handler.
 */
class Add_To_Cart_By_Sku {

	/**
	 * The query arg holding the SKU.
	 */
	const QUERY_ARG = 'add-to-cart-sku';

	/**
	 * Add the hooks.
	 */
	public function register_hooks(): void {
		// Before WooCommerce's handler, on `wp_loaded` priority 20.
		add_action( 'wp_loaded', array( $this, 'set_add_to_cart_product_id' ), 10 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'redirect_to_checkout' ) );
	}

	/**
	 * Set `add-to-cart` to the ID of the product with the requested SKU, for WooCommerce to add it to the cart.
	 *
	 * @hooked wp_loaded
	 * @see self::register_hooks()
	 * @see WC_Form_Handler::add_to_cart_action()
	 */
	public function set_add_to_cart_product_id(): void {
		$sku = $this->get_requested_sku();
		if ( null === $sku || ! function_exists( 'wc_get_product_id_by_sku' ) ) {
			return;
		}

		$product_id = wc_get_product_id_by_sku( $sku ) ?: $this->get_product_id_by_sku_meta( $sku );
		if ( 0 === $product_id ) {
			return;
		}

		$_REQUEST['add-to-cart'] = (string) $product_id;
	}

	/**
	 * Find the product by its `_sku` postmeta.
	 *
	 * `wc_get_product_id_by_sku()` reads `wc_product_meta_lookup`, which the WordPress Importer does not populate when
	 * importing the sample products.
	 *
	 * @param string $sku The product SKU.
	 * @return int The product ID, or 0 when not found.
	 */
	protected function get_product_id_by_sku_meta( string $sku ): int {
		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One lookup when the development helper link is used.
				'meta_query'     => array(
					array(
						'key'   => '_sku',
						'value' => $sku,
					),
				),
			)
		);

		return (int) ( $product_ids[0] ?? 0 );
	}

	/**
	 * Redirect to the checkout without the query arg, so reloading the page does not add the product again.
	 *
	 * @hooked woocommerce_add_to_cart_redirect
	 * @see self::register_hooks()
	 * @see WC_Form_Handler::add_to_cart_action()
	 *
	 * @param string|false $url The URL to redirect to after adding to the cart, or false to not redirect.
	 * @return string|false
	 */
	public function redirect_to_checkout( $url ) {
		return null === $this->get_requested_sku()
			? $url
			: wc_get_checkout_url();
	}

	/**
	 * The SKU in the query arg, or null when absent.
	 */
	protected function get_requested_sku(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A development helper link; WooCommerce's own `add-to-cart` link is not nonced either.
		if ( ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sku = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) );

		return '' === $sku ? null : $sku;
	}
}
