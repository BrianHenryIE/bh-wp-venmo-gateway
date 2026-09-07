<?php
/**
 * Registers Venmo gateway settings in GiveWP admin.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\API\Settings;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Venmo_Gateway as WooCommerce_Venmo_Gateway;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LogLevel;
use BrianHenryIE\WP_Venmo_Gateway\Venmo_Username;

/**
 * Registers the Venmo section and its fields on GiveWP's gateway settings tab.
 */
class Gateway_Settings {

	/**
	 * The wp_options key for the plugin-wide log level, shared with the WooCommerce gateway.
	 *
	 * @see Settings::get_log_level()
	 * @see WooCommerce_Venmo_Gateway::update_plugin_log_level_on_settings_save()
	 */
	const LOG_LEVEL_OPTION_NAME = 'bh_wp_venmo_gateway_log_level';

	/**
	 * The GiveWP settings field id for the log level, stored inside the `give_settings` option.
	 */
	const LOG_LEVEL_FIELD_ID = 'venmo_log_level';

	/**
	 * Add the "Venmo" section to GiveWP gateway settings tabs.
	 *
	 * @hooked give_get_sections_gateways
	 * @see \Give_Settings_Page::get_sections()
	 *
	 * @param string[] $sections The existing gateway settings sections, keyed by section id.
	 * @return string[]
	 */
	public function register_sections( array $sections ): array {
		$sections['venmo'] = __( 'Venmo', 'bh-wp-venmo-gateway' );
		return $sections;
	}

	/**
	 * Add settings fields to the Venmo section.
	 *
	 * @hooked give_get_settings_gateways
	 * @see \Give_Settings_Page::get_settings()
	 *
	 * @param array<int, array<string, mixed>> $settings The flat list of field definitions for the current section.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_settings( array $settings ): array {
		if ( 'venmo' !== give_get_current_setting_section() ) {
			return $settings;
		}

		$settings[] = array(
			'id'   => 'give_title_venmo',
			'type' => 'title',
			'desc' => $this->get_last_donations_summary(),
		);

		$settings[] = array(
			'name'        => __( 'Venmo @username', 'bh-wp-venmo-gateway' ),
			'desc'        => __( 'The Venmo @username that donors will be instructed to send payment to.', 'bh-wp-venmo-gateway' ),
			'id'          => 'venmo_store_username',
			'type'        => 'text',
			'placeholder' => '@username',
		);

		/**
		 * The log level is shared with the entire plugin – the WooCommerce gateway settings page edits the same value.
		 * When this section's settings are saved, the value is synced to `bh_wp_venmo_gateway_log_level`.
		 * When this section is displayed, the stored GiveWP value is overwritten by `bh_wp_venmo_gateway_log_level`,
		 * so a change made on the WooCommerce settings page is reflected here.
		 *
		 * @see self::sanitize_log_level()
		 * @see WooCommerce_Venmo_Gateway::init_form_fields()
		 */
		$log_level = $this->get_shared_log_level();
		if ( give_get_option( self::LOG_LEVEL_FIELD_ID ) !== $log_level ) {
			give_update_option( self::LOG_LEVEL_FIELD_ID, $log_level );
		}

		$settings[] = array(
			'name'    => __( 'Log Level', 'bh-wp-venmo-gateway' ),
			'desc'    => __( 'Increasingly detailed levels of logs. Shared with the WooCommerce Venmo gateway. ', 'bh-wp-venmo-gateway' ) . '<a href="' . esc_url( $this->get_logs_page_url() ) . '">' . __( 'View Logs', 'bh-wp-venmo-gateway' ) . '</a>',
			'id'      => self::LOG_LEVEL_FIELD_ID,
			'type'    => 'select',
			'options' => $this->get_log_level_options(),
			'default' => $log_level,
		);

		$settings[] = array(
			'id'   => 'give_title_venmo',
			'type' => 'sectionend',
		);

		return $settings;
	}

	/**
	 * When the log level is saved on the GiveWP settings page, update the plugin-wide log level to match.
	 *
	 * Invalid values fall back to the currently configured level.
	 *
	 * @hooked give_admin_settings_sanitize_option_venmo_log_level
	 * @see \Give_Admin_Settings::save()
	 *
	 * @param mixed $value The sanitized value about to be saved into `give_settings`.
	 */
	public function sanitize_log_level( $value ): string {
		$value = is_string( $value ) ? $value : '';

		if ( ! array_key_exists( $value, $this->get_log_level_options() ) ) {
			$value = $this->get_shared_log_level();
		}

		if ( $this->get_shared_log_level() !== $value ) {
			update_option( self::LOG_LEVEL_OPTION_NAME, $value );
		}

		return $value;
	}

	/**
	 * The plugin-wide log level, as configured on either the WooCommerce or GiveWP gateway settings page.
	 *
	 * @see Settings::get_log_level()
	 */
	private function get_shared_log_level(): string {
		return get_option( self::LOG_LEVEL_OPTION_NAME, LogLevel::NOTICE );
	}

	/**
	 * The selectable log levels, keyed by the PSR log level name, with "none" to disable logging.
	 *
	 * @see WooCommerce_Venmo_Gateway::init_form_fields() – the same list is used on the WooCommerce settings page.
	 *
	 * @return array<string, string>
	 */
	private function get_log_level_options(): array {
		$log_levels = array( 'none', LogLevel::ERROR, LogLevel::WARNING, LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG );

		$options = array();
		foreach ( $log_levels as $log_level ) {
			$options[ $log_level ] = ucfirst( $log_level );
		}

		return $options;
	}

	/**
	 * The URL of the plugin's logs page, registered by the logger library.
	 *
	 * @see WooCommerce_Venmo_Gateway::init_form_fields()
	 */
	private function get_logs_page_url(): string {
		return admin_url( 'admin.php?page=bh-wp-venmo-gateway-logs' );
	}

	/**
	 * Strip any leading "@" from the store username when the setting is saved.
	 *
	 * @hooked give_admin_settings_sanitize_option_venmo_store_username
	 * @see \Give_Admin_Settings::save()
	 *
	 * @param mixed $value The sanitized value about to be saved.
	 */
	public function sanitize_store_username( $value ): string {
		return Venmo_Username::sanitize( (string) $value );
	}

	/**
	 * Build the "most recent Venmo donation" summary shown at the top of the
	 * settings section: the date of the last completed, pending and abandoned
	 * Venmo donation, or "Never" when there are none.
	 */
	private function get_last_donations_summary(): string {
		$statuses = array(
			'publish'   => __( 'Completed', 'bh-wp-venmo-gateway' ),
			'pending'   => __( 'Pending', 'bh-wp-venmo-gateway' ),
			'abandoned' => __( 'Abandoned', 'bh-wp-venmo-gateway' ),
		);

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$items = '';
		foreach ( $statuses as $status => $label ) {
			$payments = give_get_payments(
				array(
					'number'  => 1,
					'gateway' => Venmo_Gateway::id(),
					'status'  => $status,
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);

			// Default to "Never"; only overwrite when there is a payment with a parseable date
			// (an empty/corrupt post_date would otherwise render as the "1 January 1970" epoch).
			$when = __( 'Never', 'bh-wp-venmo-gateway' );
			if ( ! empty( $payments ) ) {
				// give_get_payments() returns WP_Post objects; the date is post_date.
				$post_date = get_post_field( 'post_date', $payments[0]->ID );
				$timestamp = is_string( $post_date ) ? strtotime( $post_date ) : false;
				if ( false !== $timestamp ) {
					$when = date_i18n( $date_format, $timestamp );
				}
			}

			$items .= sprintf( '<li>%1$s: %2$s</li>', esc_html( $label ), esc_html( $when ) );
		}

		return sprintf(
			'<div class="bh-venmo-last-donations"><strong>%1$s</strong><ul>%2$s</ul></div>',
			esc_html__( 'Most recent Venmo donation', 'bh-wp-venmo-gateway' ),
			$items
		);
	}
}
