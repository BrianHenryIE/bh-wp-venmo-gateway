<?php
/**
 * Filters the WooCommerce admin orders list to Venmo orders awaiting payment, via a query arg the
 * gateway settings page links to.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Works with both the HPOS list table (`admin.php?page=wc-orders`) and the legacy posts list
 * (`edit.php?post_type=shop_order`).
 *
 * @see Venmo_Gateway::init_form_fields()
 */
class Orders_List_Filter {

	/**
	 * The query arg that switches the filter on: `?bh_wp_venmo_gateway_awaiting_payment=1`.
	 */
	const QUERY_ARG = 'bh_wp_venmo_gateway_awaiting_payment';

	/**
	 * The WooCommerce payment method id of the Venmo gateway.
	 *
	 * @see Venmo_Gateway::__construct()
	 */
	const PAYMENT_METHOD_ID = 'venmo';

	/**
	 * Order statuses that count as "awaiting payment", `wc-` prefixed as the list tables expect.
	 *
	 * @var string[]
	 */
	const AWAITING_PAYMENT_STATUSES = array( 'wc-pending', 'wc-on-hold' );

	/**
	 * The URL of the admin orders list filtered to pending and on-hold Venmo orders.
	 */
	public function get_awaiting_payment_orders_url(): string {
		$orders_list_url = $this->is_hpos_enabled()
			? admin_url( 'admin.php?page=wc-orders' )
			: admin_url( 'edit.php?post_type=shop_order' );

		return add_query_arg( self::QUERY_ARG, '1', $orders_list_url );
	}

	/**
	 * HPOS: restrict the `wc_get_orders()` query to Venmo orders in the awaiting-payment statuses.
	 *
	 * @hooked woocommerce_order_list_table_prepare_items_query_args
	 * @see \Automattic\WooCommerce\Internal\Admin\Orders\ListTable::prepare_items()
	 *
	 * @param array<string, mixed> $query_args Arguments to be passed to `wc_get_orders()`.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_hpos_list_table_query_args( array $query_args ): array {
		if ( ! $this->is_filter_requested() ) {
			return $query_args;
		}

		$query_args['payment_method'] = self::PAYMENT_METHOD_ID;
		$query_args['status']         = self::AWAITING_PAYMENT_STATUSES;

		return $query_args;
	}

	/**
	 * Legacy posts list: restrict the `WP_Query` to Venmo orders in the awaiting-payment statuses.
	 *
	 * @hooked request
	 * @see \WC_Admin_List_Table::request_query()
	 *
	 * @param array<string, mixed> $query_vars The main query's vars.
	 *
	 * @return array<string, mixed>
	 */
	public function filter_legacy_list_table_query_vars( array $query_vars ): array {
		if ( ! $this->is_filter_requested() || ! is_admin() || 'shop_order' !== ( $query_vars['post_type'] ?? '' ) ) {
			return $query_vars;
		}

		$query_vars['post_status'] = self::AWAITING_PAYMENT_STATUSES;
		$query_vars['meta_query']  = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => '_payment_method',
				'value' => self::PAYMENT_METHOD_ID,
			),
		);

		return $query_vars;
	}

	/**
	 * Is the current request for the filtered list?
	 */
	protected function is_filter_requested(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		return isset( $_GET[ self::QUERY_ARG ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) );
	}

	/**
	 * Are orders stored in WooCommerce's custom tables (HPOS) rather than as posts?
	 */
	protected function is_hpos_enabled(): bool {
		return class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
