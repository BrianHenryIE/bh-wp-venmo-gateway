<?php
/**
 * Version 4.3.0 renamed the plugin from `bh-wp-venmo-gateway` to `juiced-venmo-gateway-pro`, which renamed its options,
 * cron hooks and meta keys.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Upgrade;

use JuicedPlugins\Venmo_Gateway_Pro\Includes\Activator;

/**
 * Moves options, cron events and meta saved under the old names to their new names.
 *
 * New names are written as literals rather than referencing the current constants so the upgrade is unaffected by
 * later renames.
 */
class V430 {

	/**
	 * The version this upgrade brings the site to.
	 */
	const VERSION = '4.3.0';

	/**
	 * Each activation is recorded as `[ ATOM datetime => plugin version ]`.
	 *
	 * @see Activator::activate()
	 */
	const ACTIVATED_TIME_OPTION_NAME = 'juiced_venmo_gateway_pro_activated_time';

	/**
	 * Old option name => new option name.
	 *
	 * Includes the options bh-wp-logger and bh-wp-order-email-reconcile derive from the plugin slug.
	 */
	const OPTION_RENAMES = array(
		'bh_wp_venmo_gateway_activated_time'              => self::ACTIVATED_TIME_OPTION_NAME,
		'bh_wp_venmo_gateway_log_level'                   => 'juiced_venmo_gateway_pro_log_level',
		'bh-wp-venmo-gateway-recent-error-data'           => 'juiced-venmo-gateway-pro-recent-error-data',
		'bh-wp-venmo-gateway-last-logs-view-time'         => 'juiced-venmo-gateway-pro-last-logs-view-time',
		'wptrt_notice_dismissed_bh-wp-venmo-gateway-recent-error' => 'wptrt_notice_dismissed_juiced-venmo-gateway-pro-recent-error',
		'bh_wp_oer_bh_wp_venmo_gateway_has_unpaid_orders' => 'bh_wp_oer_juiced_venmo_gateway_pro_has_unpaid_orders',
	);

	/**
	 * Options which the libraries save with autoload off.
	 */
	const NOT_AUTOLOADED = array(
		'bh_wp_oer_juiced_venmo_gateway_pro_has_unpaid_orders',
	);

	/**
	 * Options with no new equivalent: email attachments (bh-wp-private-uploads) are now disabled.
	 *
	 * @see \JuicedPlugins\Venmo_Gateway_Pro\API\Settings::get_private_uploads_directory_name()
	 */
	const STALE_OPTIONS = array(
		'wptrt_notice_dismissed_bh-wp-v-email-attach-private-uploads-url-is-public',
	);

	/**
	 * Old cron hook => new cron hook. Pending events are moved, keeping their time, schedule and args.
	 *
	 * @see \JuicedPlugins\Venmo_Gateway_Pro\Includes\Cron
	 */
	const CRON_RENAMES = array(
		'bh_wp_venmo_gateway_check_for_payment_emails'     => 'juiced_venmo_gateway_pro_check_for_payment_emails',
		'bh_wp_venmo_gateway_fetch_customer_venmo_profile' => 'juiced_venmo_gateway_pro_fetch_customer_venmo_profile',
		'bh_wp_venmo_gateway_fetch_donor_venmo_profile'    => 'juiced_venmo_gateway_pro_fetch_donor_venmo_profile',
	);

	/**
	 * Cron hooks to unschedule.
	 *
	 * The bh-wp-logger library schedules its own daily job under the new slug. The bh-wp-private-uploads jobs, old
	 * and new names, are for email attachments, which are now disabled.
	 */
	const STALE_CRON_HOOKS = array(
		'delete_logs_bh-wp-venmo-gateway',
		'bh_wp_venmo_gateway_private_uploads_check_url_bh_wp_v_email_attach',
		'bh_wp_venmo_gateway_private_uploads_unsnooze_dismissed_notice_bh_wp_v_email_attach',
		'juiced_venmo_gateway_pro_private_uploads_check_url_juiced__email_attach',
		'juiced_venmo_gateway_pro_private_uploads_unsnooze_dismissed_notice_juiced__email_attach',
	);

	/**
	 * Old meta key => new meta key, for WooCommerce orders, GiveWP donations and users.
	 */
	const META_KEY_RENAMES = array(
		'_customer-venmo-username'            => '_customer_venmo_username',
		'_destination-account-venmo-username' => '_destination_account_venmo_username',
		'_customer-venmo-display-name'        => '_customer_venmo_display_name',
		'_venmo-transaction-id'               => '_venmo_transaction_id',
		'_venmo-payment-date'                 => '_venmo_payment_date',
	);

	/**
	 * While true, the old option names are read and written as stored, i.e. not redirected to the new names.
	 *
	 * Static because the activator's instance runs the upgrade while the plugin's own instance's filters are added.
	 *
	 * @var bool
	 */
	protected static bool $is_accessing_stored_values = false;

	/**
	 * Add the filters redirecting the old option names to the new.
	 */
	public function register_hooks(): void {
		foreach ( array_keys( self::OPTION_RENAMES ) as $old_option_name ) {
			add_filter( "pre_option_{$old_option_name}", array( $this, 'rename_options_on_the_fly' ), 10, 2 );
			add_filter( "pre_update_option_{$old_option_name}", array( $this, 'redirect_update_option' ), 10, 3 );
			add_action( "add_option_{$old_option_name}", array( $this, 'redirect_add_option' ), 10, 2 );
		}
	}

	/**
	 * Runs once so old options, cron events and meta are written to their new names.
	 *
	 * @see Upgrader::do_upgrades()
	 */
	public function rename_options_once(): void {
		self::$is_accessing_stored_values = true;
		try {
			$this->rename_options();
			$this->rename_cron_events();
			$this->rename_meta_keys();
		} finally {
			self::$is_accessing_stored_values = false;
		}
	}

	/**
	 * Copy each old option to its new name, then delete the old option.
	 *
	 * An option already saved under its new name is kept, except the activation times, which are merged.
	 */
	protected function rename_options(): void {
		$absent = new \stdClass();

		foreach ( self::OPTION_RENAMES as $old_option_name => $new_option_name ) {
			$old_value = get_option( $old_option_name, $absent );

			if ( $absent === $old_value ) {
				continue;
			}

			$new_value = get_option( $new_option_name, $absent );

			if ( self::ACTIVATED_TIME_OPTION_NAME === $new_option_name ) {
				$new_value = array_merge(
					is_array( $old_value ) ? $old_value : array(),
					is_array( $new_value ) ? $new_value : array()
				);
				update_option( $new_option_name, $new_value );
			} elseif ( $absent === $new_value ) {
				update_option(
					$new_option_name,
					$old_value,
					in_array( $new_option_name, self::NOT_AUTOLOADED, true ) ? false : null
				);
			}

			delete_option( $old_option_name );
		}

		foreach ( self::STALE_OPTIONS as $stale_option_name ) {
			delete_option( $stale_option_name );
		}
	}

	/**
	 * Move pending events from the old cron hooks to the new, and unschedule the stale hooks.
	 *
	 * An event is dropped rather than moved when the same event (hook and args) is already scheduled under the new name.
	 */
	protected function rename_cron_events(): void {
		foreach ( _get_cron_array() as $timestamp => $hooks ) {
			foreach ( $hooks as $old_hook => $events ) {
				if ( ! isset( self::CRON_RENAMES[ $old_hook ] ) ) {
					continue;
				}
				$new_hook = self::CRON_RENAMES[ $old_hook ];

				foreach ( $events as $event ) {
					// WordPress stores cron args as a list.
					$args = array_values( $event['args'] );

					wp_unschedule_event( $timestamp, $old_hook, $args );

					if ( false !== wp_next_scheduled( $new_hook, $args ) ) {
						continue;
					}

					if ( false === $event['schedule'] ) {
						wp_schedule_single_event( $timestamp, $new_hook, $args );
					} else {
						wp_schedule_event( $timestamp, $event['schedule'], $new_hook, $args );
					}
				}
			}
		}

		foreach ( self::STALE_CRON_HOOKS as $stale_hook ) {
			wp_unschedule_hook( $stale_hook );
		}
	}

	/**
	 * Rename the meta keys on WooCommerce orders (posts and HPOS), GiveWP donations and users.
	 *
	 * Where an object already has the new key, its old row is deleted rather than duplicating the key.
	 */
	protected function rename_meta_keys(): void {
		global $wpdb;

		$tables = array(
			$wpdb->postmeta                     => 'post_id',
			$wpdb->usermeta                     => 'user_id',
			$wpdb->prefix . 'wc_orders_meta'    => 'order_id',
			$wpdb->prefix . 'give_donationmeta' => 'donation_id',
			$wpdb->prefix . 'give_paymentmeta'  => 'payment_id',
		);

		foreach ( $tables as $table => $object_id_column ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				continue;
			}

			foreach ( self::META_KEY_RENAMES as $old_meta_key => $new_meta_key ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						'DELETE old_rows FROM %i AS old_rows INNER JOIN %i AS new_rows ON new_rows.%i = old_rows.%i AND new_rows.meta_key = %s WHERE old_rows.meta_key = %s',
						$table,
						$table,
						$object_id_column,
						$object_id_column,
						$new_meta_key,
						$old_meta_key
					)
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare(
						'UPDATE %i SET meta_key = %s WHERE meta_key = %s',
						$table,
						$new_meta_key,
						$old_meta_key
					)
				);
			}
		}

		// The meta caches hold the old keys.
		wp_cache_flush();
	}

	/**
	 * Handles reads to the old option name, e.g. if someone's `wp option get x` still expects it.
	 *
	 * Before the upgrade has run, the new option is absent and the stored old value is read as normal.
	 *
	 * @hooked pre_option_{$old_option_name}
	 * @see V430::register_hooks()
	 *
	 * @param mixed  $pre_option The value to short-circuit with, `false` to continue reading the option.
	 * @param string $old_option_name The option name being read.
	 * @return mixed
	 */
	public function rename_options_on_the_fly( mixed $pre_option, string $old_option_name ): mixed {
		if ( self::$is_accessing_stored_values || false !== $pre_option || ! isset( self::OPTION_RENAMES[ $old_option_name ] ) ) {
			return $pre_option;
		}

		$absent    = new \stdClass();
		$new_value = get_option( self::OPTION_RENAMES[ $old_option_name ], $absent );

		return $absent === $new_value
			? $pre_option
			: $new_value;
	}

	/**
	 * Saves writes to the old option name under the new name instead.
	 *
	 * Returning the old value stops `update_option()` writing the old name, so it returns false.
	 *
	 * @hooked pre_update_option_{$old_option_name}
	 * @see V430::register_hooks()
	 * @see update_option()
	 *
	 * @param mixed  $value The value being saved.
	 * @param mixed  $old_value The current value, as read through {@see V430::rename_options_on_the_fly()}.
	 * @param string $old_option_name The option name being written.
	 * @return mixed
	 */
	public function redirect_update_option( mixed $value, mixed $old_value, string $old_option_name ): mixed {
		if ( self::$is_accessing_stored_values || ! isset( self::OPTION_RENAMES[ $old_option_name ] ) ) {
			return $value;
		}

		update_option( self::OPTION_RENAMES[ $old_option_name ], $value );

		return $old_value;
	}

	/**
	 * Moves an option added under the old name to the new name.
	 *
	 * `add_option()` cannot be short-circuited, so the old option is deleted after it is added.
	 *
	 * @hooked add_option_{$old_option_name}
	 * @see V430::register_hooks()
	 *
	 * @param string $old_option_name The option name added.
	 * @param mixed  $value The value added.
	 */
	public function redirect_add_option( string $old_option_name, mixed $value ): void {
		if ( self::$is_accessing_stored_values || ! isset( self::OPTION_RENAMES[ $old_option_name ] ) ) {
			return;
		}

		update_option( self::OPTION_RENAMES[ $old_option_name ], $value );

		self::$is_accessing_stored_values = true;
		try {
			delete_option( $old_option_name );
		} finally {
			self::$is_accessing_stored_values = false;
		}
	}
}
