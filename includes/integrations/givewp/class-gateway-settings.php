<?php
/**
 * Registers Venmo gateway settings in GiveWP admin.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

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
}
