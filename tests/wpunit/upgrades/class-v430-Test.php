<?php
/**
 * Tests the v4.3.0 option renames against the real options table.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Upgrade;

use JuicedPlugins\Venmo_Gateway_Pro\WPUnit_Testcase;

/**
 * @coversDefaultClass \JuicedPlugins\Venmo_Gateway_Pro\Upgrade\V430
 */
class V430_Test extends WPUnit_Testcase {

	/**
	 * @covers ::rename_options_once
	 */
	public function test_old_options_are_moved_to_new_names(): void {
		update_option( 'bh_wp_venmo_gateway_log_level', 'debug' );
		update_option( 'bh_wp_oer_bh_wp_venmo_gateway_has_unpaid_orders', 'yes', false );

		( new V430() )->rename_options_once();

		$this->assertSame( 'debug', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertSame( 'yes', get_option( 'bh_wp_oer_juiced_venmo_gateway_pro_has_unpaid_orders' ) );
		$this->assertFalse( get_option( 'bh_wp_venmo_gateway_log_level' ) );
		$this->assertFalse( get_option( 'bh_wp_oer_bh_wp_venmo_gateway_has_unpaid_orders' ) );
	}

	/**
	 * @covers ::rename_options_once
	 */
	public function test_existing_new_option_is_not_overwritten(): void {
		update_option( 'bh_wp_venmo_gateway_log_level', 'debug' );
		update_option( 'juiced_venmo_gateway_pro_log_level', 'error' );

		( new V430() )->rename_options_once();

		$this->assertSame( 'error', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertFalse( get_option( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * @covers ::rename_options_once
	 */
	public function test_activation_times_are_merged(): void {
		update_option( 'bh_wp_venmo_gateway_activated_time', array( '2025-01-01T00:00:00+00:00' => '4.2.0' ) );
		update_option( 'juiced_venmo_gateway_pro_activated_time', array( '2025-06-01T00:00:00+00:00' => '4.2.1' ) );

		( new V430() )->rename_options_once();

		$activated_times = get_option( 'juiced_venmo_gateway_pro_activated_time' );
		$this->assertIsArray( $activated_times );
		$this->assertSame( '4.2.0', $activated_times['2025-01-01T00:00:00+00:00'] );
		$this->assertSame( '4.2.1', $activated_times['2025-06-01T00:00:00+00:00'] );
		$this->assertFalse( get_option( 'bh_wp_venmo_gateway_activated_time' ) );
	}

	/**
	 * @covers ::rename_options_on_the_fly
	 */
	public function test_old_option_name_reads_new_value(): void {
		( new V430() )->register_hooks();
		update_option( 'juiced_venmo_gateway_pro_log_level', 'warning' );

		$this->assertSame( 'warning', get_option( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * Before the upgrade has run, the old option's stored value is still read.
	 *
	 * @covers ::rename_options_on_the_fly
	 */
	public function test_old_option_name_reads_stored_value_when_new_option_absent(): void {
		( new V430() )->register_hooks();
		update_option( 'bh_wp_venmo_gateway_log_level', 'info' );

		$this->assertSame( 'info', get_option( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * The upgrade must read the stored old value even when another instance's filters are added.
	 *
	 * @covers ::rename_options_once
	 */
	public function test_rename_reads_stored_value_when_filters_are_added(): void {
		update_option( 'bh_wp_venmo_gateway_activated_time', array( '2025-01-01T00:00:00+00:00' => '4.2.0' ) );
		update_option( 'juiced_venmo_gateway_pro_activated_time', array( '2025-06-01T00:00:00+00:00' => '4.2.1' ) );
		( new V430() )->register_hooks();

		( new V430() )->rename_options_once();

		$activated_times = get_option( 'juiced_venmo_gateway_pro_activated_time' );
		$this->assertIsArray( $activated_times );
		$this->assertArrayHasKey( '2025-01-01T00:00:00+00:00', $activated_times );
	}

	/**
	 * Whether the option has a row in the options table, bypassing the filters.
	 *
	 * @param string $option_name The option name.
	 */
	protected function option_row_exists( string $option_name ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $option_name ) );
	}

	/**
	 * @covers ::redirect_update_option
	 */
	public function test_update_to_old_option_name_writes_new_option(): void {
		( new V430() )->register_hooks();
		update_option( 'juiced_venmo_gateway_pro_log_level', 'warning' );

		update_option( 'bh_wp_venmo_gateway_log_level', 'error' );

		$this->assertSame( 'error', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertFalse( $this->option_row_exists( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * When neither option exists, `update_option()` would add the old option.
	 *
	 * @covers ::redirect_update_option
	 */
	public function test_update_to_absent_old_option_name_writes_new_option(): void {
		( new V430() )->register_hooks();

		update_option( 'bh_wp_venmo_gateway_log_level', 'error' );

		$this->assertSame( 'error', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertFalse( $this->option_row_exists( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * @covers ::redirect_add_option
	 */
	public function test_add_old_option_name_moves_to_new_option(): void {
		( new V430() )->register_hooks();

		add_option( 'bh_wp_venmo_gateway_log_level', 'critical' );

		$this->assertSame( 'critical', get_option( 'juiced_venmo_gateway_pro_log_level' ) );
		$this->assertFalse( $this->option_row_exists( 'bh_wp_venmo_gateway_log_level' ) );
	}

	/**
	 * Email attachments are disabled, so their dismissed-notice option is deleted rather than renamed.
	 *
	 * @covers ::rename_options_once
	 */
	public function test_stale_option_is_deleted(): void {
		update_option( 'wptrt_notice_dismissed_bh-wp-v-email-attach-private-uploads-url-is-public', 'yes' );

		( new V430() )->rename_options_once();

		$this->assertFalse( $this->option_row_exists( 'wptrt_notice_dismissed_bh-wp-v-email-attach-private-uploads-url-is-public' ) );
	}

	/**
	 * @covers ::rename_options_once
	 */
	public function test_cron_events_are_moved_to_new_hooks(): void {
		$timestamp = time() + HOUR_IN_SECONDS;
		wp_schedule_single_event( $timestamp, 'bh_wp_venmo_gateway_fetch_customer_venmo_profile', array( array( 'order_id' => 123 ) ) );
		wp_schedule_event( $timestamp, 'hourly', 'bh_wp_venmo_gateway_check_for_payment_emails' );

		( new V430() )->rename_options_once();

		$this->assertFalse( wp_next_scheduled( 'bh_wp_venmo_gateway_fetch_customer_venmo_profile', array( array( 'order_id' => 123 ) ) ) );
		$this->assertSame( $timestamp, wp_next_scheduled( 'juiced_venmo_gateway_pro_fetch_customer_venmo_profile', array( array( 'order_id' => 123 ) ) ) );

		$this->assertFalse( wp_next_scheduled( 'bh_wp_venmo_gateway_check_for_payment_emails' ) );
		$event = wp_get_scheduled_event( 'juiced_venmo_gateway_pro_check_for_payment_emails' );
		$this->assertNotFalse( $event );
		$this->assertSame( 'hourly', $event->schedule );
	}

	/**
	 * An old event is dropped when the same event is already scheduled under the new hook.
	 *
	 * @covers ::rename_options_once
	 */
	public function test_cron_event_already_scheduled_under_new_hook_is_not_duplicated(): void {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'bh_wp_venmo_gateway_check_for_payment_emails' );
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'juiced_venmo_gateway_pro_check_for_payment_emails' );

		( new V430() )->rename_options_once();

		$this->assertFalse( wp_next_scheduled( 'bh_wp_venmo_gateway_check_for_payment_emails' ) );

		$count = 0;
		foreach ( _get_cron_array() as $hooks ) {
			$count += count( $hooks['juiced_venmo_gateway_pro_check_for_payment_emails'] ?? array() );
		}
		$this->assertSame( 1, $count );
	}

	/**
	 * @covers ::rename_options_once
	 */
	public function test_stale_cron_events_are_unscheduled(): void {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'delete_logs_bh-wp-venmo-gateway' );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'juiced_venmo_gateway_pro_private_uploads_check_url_juiced__email_attach' );

		( new V430() )->rename_options_once();

		$this->assertFalse( wp_next_scheduled( 'delete_logs_bh-wp-venmo-gateway' ) );
		$this->assertFalse( wp_next_scheduled( 'juiced_venmo_gateway_pro_private_uploads_check_url_juiced__email_attach' ) );
	}

	/**
	 * @covers ::rename_options_once
	 */
	public function test_post_and_user_meta_keys_are_renamed(): void {
		$post_id = wp_insert_post( array( 'post_title' => 'Order' ) );
		$user_id = wp_insert_user(
			array(
				'user_login' => 'customer',
				'user_pass'  => 'password',
			)
		);
		$this->assertIsInt( $user_id );
		add_post_meta( $post_id, '_customer-venmo-username', 'brianhenryie' );
		add_post_meta( $post_id, '_destination-account-venmo-username', 'store' );
		add_user_meta( $user_id, '_customer-venmo-username', 'brianhenryie' );

		( new V430() )->rename_options_once();

		$this->assertSame( 'brianhenryie', get_post_meta( $post_id, '_customer_venmo_username', true ) );
		$this->assertSame( 'store', get_post_meta( $post_id, '_destination_account_venmo_username', true ) );
		$this->assertSame( 'brianhenryie', get_user_meta( $user_id, '_customer_venmo_username', true ) );
		$this->assertSame( array(), get_post_meta( $post_id, '_customer-venmo-username', false ) );
		$this->assertSame( array(), get_user_meta( $user_id, '_customer-venmo-username', false ) );
	}

	/**
	 * When the object already has the new key, the old row is deleted rather than duplicating the key.
	 *
	 * @covers ::rename_options_once
	 */
	public function test_meta_key_already_present_under_new_name_is_kept(): void {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'customer',
				'user_pass'  => 'password',
			)
		);
		$this->assertIsInt( $user_id );
		add_user_meta( $user_id, '_customer-venmo-username', 'old-username' );
		add_user_meta( $user_id, '_customer_venmo_username', 'new-username' );

		( new V430() )->rename_options_once();

		$this->assertSame( array( 'new-username' ), get_user_meta( $user_id, '_customer_venmo_username', false ) );
		$this->assertSame( array(), get_user_meta( $user_id, '_customer-venmo-username', false ) );
	}

	/**
	 * @covers ::rename_options_once
	 */
	public function test_hpos_order_meta_keys_are_renamed(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_orders_meta';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class )->create_database_tables();
		}
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->markTestSkipped( 'HPOS table not installed.' );
		}
		$wpdb->insert(
			$table,
			array(
				'order_id'   => 987654,
				'meta_key'   => '_customer-venmo-display-name', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Inserting a fixture row, not querying.
				'meta_value' => 'Brian Henry', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Inserting a fixture row, not querying.
			)
		);

		( new V430() )->rename_options_once();

		$this->assertSame(
			'_customer_venmo_display_name',
			$wpdb->get_var( $wpdb->prepare( 'SELECT meta_key FROM %i WHERE order_id = %d', $table, 987654 ) )
		);
	}
}
