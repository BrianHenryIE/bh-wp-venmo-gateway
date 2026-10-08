<?php
/**
 * Authentication conveniences for development and E2E testing.
 *
 * REST authentication in the Playwright tests is handled by `@wordpress/e2e-test-utils-playwright`
 * (`requestUtils` – cookie + nonce from `artifacts/storage-states/admin.json`). Logging in as
 * another user during development is handled by the `user-switching` and `bh-wp-autologin-urls`
 * plugins, which `wp-env` installs and activates.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin;

/**
 * Disable the WooCommerce Store API nonce check so the E2E helpers can call it directly.
 */
class Authentication {

	/**
	 * Add actions/filters.
	 */
	public function register_hooks(): void {
		/**
		 * @see \Automattic\WooCommerce\StoreApi\Routes\V1\AbstractCartRoute::check_nonce()
		 */
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
	}
}
