<?php
/**
 * The plugin page output of the plugin.
 * Adds a "Settings" link
 * Adds an "Orders" link when Filter WooCommerce Orders by Payment Method plugin is installed.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Admin;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\Admin\Unreconciled_Orders_Page;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Venmo_Gateway;
use WC_Payment_Gateway;
use WC_Payment_Gateways;

/**
 * Checks are the relevant plugins (WooCommerce, the filter one) present before adding links.
 */
class Plugins_Page {

	/**
	 * Adds 'Settings' link to the configuration under WooCommerce's payment gateway settings page.
	 *
	 * @hooked plugin_action_links_{plugin basename}
	 * @see \WP_Plugins_List_Table::display_rows()
	 *
	 * @param string[] $links_array The links that will be shown below the plugin name on plugins.php (usually "Deactivate").
	 *
	 * @return string[]
	 */
	public function add_settings_action_link( array $links_array ): array {

		if ( ! class_exists( WC_Payment_Gateways::class ) ) {
			return $links_array;
		}

		$setting_link   = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=venmo' );
		$plugin_links   = array();
		$plugin_links[] = '<a href="' . $setting_link . '">' . __( 'Settings', 'bh-wp-venmo-gateway' ) . '</a>';

		return array_merge( $plugin_links, $links_array );
	}

	/**
	 * Adds 'Orders' link if Filter WooCommerce Orders by Payment Method plugin is installed.
	 *
	 * @hooked plugin_action_links_{plugin basename}
	 * @see \WP_Plugins_List_Table::display_rows()
	 *
	 * @param string[] $links_array The links that will be shown below the plugin name on plugins.php (usually "Deactivate").
	 *
	 * @return string[]
	 */
	public function add_orders_action_link( array $links_array ): array {

		$plugin_links = array();

		/**
		 * Add an "Orders" link to a filtered list of orders if the Filter WooCommerce Orders by Payment Method plugin is installed.
		 *
		 * @see https://www.skyverge.com/blog/filtering-woocommerce-orders/
		 */
		if ( is_plugin_active( 'wc-filter-orders-by-payment/filter-wc-orders-by-gateway.php' ) && class_exists( WC_Payment_Gateway::class ) ) {

			$params = array(
				'post_type'                  => 'shop_order',
				'_shop_order_payment_method' => 'venmo',
			);

			$orders_link    = add_query_arg( $params, admin_url( 'edit.php' ) );
			$plugin_links[] = '<a href="' . $orders_link . '">' . __( 'Orders', 'bh-wp-venmo-gateway' ) . '</a>';
		}

		return array_merge( $plugin_links, $links_array );
	}

	/**
	 * Add an "Unreconciled orders" link to the reconcile library's page listing orders awaiting a payment email.
	 *
	 * This view lists both WooCommerce and GiveWP orders, which is why we do not link to their native view directly.
	 * TODO: Check what plugins are active and prefer the native view.
	 *
	 * @hooked plugin_action_links_{$plugin_basename}
	 * @see \WP_Plugins_List_Table::single_row()
	 * @see Unreconciled_Orders_Menu
	 *
	 * @param string[] $links_array The links that will be shown below the plugin name on plugins.php.
	 *
	 * @return string[]
	 */
	public function add_unreconciled_orders_action_link( array $links_array ): array {
		$url = admin_url( 'admin.php?page=' . Unreconciled_Orders_Page::PAGE_SLUG );

		array_unshift( $links_array, '<a href="' . esc_url( $url ) . '">' . __( 'Unreconciled orders', 'bh-wp-venmo-gateway' ) . '</a>' );

		return $links_array;
	}
}
