<?php
/**
 * Registers Venmo gateway settings in GiveWP admin.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\Venmo_Username;

/**
 * Registers the Venmo section and its fields on GiveWP's gateway settings tab.
 */
class Gateway_Settings {

	/**
	 * Add the "Venmo" section to GiveWP gateway settings tabs.
	 *
	 * @hooked give_get_sections_gateways
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

		$settings[] = array(
			'id'   => 'give_title_venmo',
			'type' => 'sectionend',
		);

		return $settings;
	}

	/**
	 * Strip any leading "@" from the store username when the setting is saved.
	 *
	 * @hooked give_admin_settings_sanitize_option_venmo_store_username
	 * @see \Give_Admin_Settings::save()
	 *
	 * @param mixed $value The sanitized value about to be saved.
	 * @return string The bare Venmo username.
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

			if ( empty( $payments ) ) {
				$when = __( 'Never', 'bh-wp-venmo-gateway' );
			} else {
				// give_get_payments() returns WP_Post objects; the date is post_date.
				$post_date = get_post_field( 'post_date', $payments[0]->ID );
				$when      = date_i18n( $date_format, (int) strtotime( is_string( $post_date ) ? $post_date : '' ) );
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
