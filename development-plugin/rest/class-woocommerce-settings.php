<?php
/**
 * Add additional settings (`woocommerce_checkout_page_id`, `juiced_venmo_gateway_pro_log_level`) to  `/wp-json/wp/v2/settings`.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest;

/**
 * A bit hacky – modify `global $wp_registered_settings` before it is used.
 */
class WooCommerce_Settings {

	/**
	 * Add hooks to register the REST endpoints.
	 */
	public function register_hooks(): void {
		add_filter( 'rest_pre_dispatch', array( $this, 'show_settings_in_rest' ) );
	}

	/**
	 * Expose settings through the REST API.
	 *
	 * `woocommerce_checkout_page_id`
	 * `juiced_venmo_gateway_pro_log_level`
	 *
	 * @hooked rest_pre_dispatch
	 *
	 * @param null|mixed $short_circuit The value to return.
	 *
	 * @see get_registered_settings
	 * /wp-json/wp/v2/settings
	 *
	 * @see WP_REST_Settings_Controller
	 */
	public function show_settings_in_rest( mixed $short_circuit ): mixed {
		global $wp_registered_settings;

		if ( ! in_array( 'woocommerce_checkout_page_id', $wp_registered_settings, true ) ) {
			$wp_registered_settings['woocommerce_checkout_page_id'] = array(
				'show_in_rest'      => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			);
		}

		if ( ! array_key_exists( 'juiced_venmo_gateway_pro_log_level', $wp_registered_settings ) ) {
			$wp_registered_settings['juiced_venmo_gateway_pro_log_level'] = array(
				'show_in_rest'      => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'notice',
			);
		}

		return $short_circuit;
	}
}
