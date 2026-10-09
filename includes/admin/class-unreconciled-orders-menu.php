<?php
/**
 * Registers the reconcile library's "Unreconciled orders" page as a hidden admin page.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Admin;

use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerInterface;
use JuicedPlugins\Venmo_Gateway_Pro\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_Page;

/**
 * The page lists the WooCommerce orders and GiveWP donations still waiting for a Venmo payment email.
 * It is not shown in the admin menu; it is linked from plugins.php and reachable at its URL.
 *
 * @see Unreconciled_Orders_Page
 */
class Unreconciled_Orders_Menu {

	/**
	 * An empty parent slug registers the page without a menu item: reachable at `admin.php?page=...` but not listed.
	 *
	 * (Registering under the WooCommerce menu and calling `remove_submenu_page()` would make the page inaccessible,
	 * because `user_can_access_admin_page()` looks the page up in its parent's submenu.)
	 *
	 * @see add_submenu_page()
	 * @see user_can_access_admin_page()
	 */
	const PARENT_SLUG = '';

	/**
	 * @param API_Interface      $api          Provides the unpaid orders provider the page lists.
	 * @param Settings_Interface $settings     The plugin settings.
	 * @param Capabilities       $capabilities Decides the capability the page requires for the current user.
	 * @param LoggerInterface    $logger       PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Settings_Interface $settings,
		protected Capabilities $capabilities,
		protected LoggerInterface $logger,
	) {
	}

	/**
	 * Register the page as a hidden admin page.
	 *
	 * @hooked admin_menu
	 * @see Unreconciled_Orders_Page::register_submenu()
	 */
	public function register_submenu(): void {
		$this->get_page()->register_submenu( self::PARENT_SLUG, $this->capabilities->get_payment_emails_capability() );
	}

	/**
	 * The page URL, for linking from elsewhere in the plugin.
	 */
	public function get_url(): string {
		return $this->get_page()->get_url();
	}

	/**
	 * Instantiate the library's page with the plugin's aggregate unpaid orders provider.
	 */
	protected function get_page(): Unreconciled_Orders_Page {
		return new Unreconciled_Orders_Page( $this->api->get_unpaid_orders_provider(), $this->settings, $this->logger );
	}
}
