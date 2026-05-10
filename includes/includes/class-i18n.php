<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

class I18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain(): void {

		load_plugin_textdomain(
			'bh-wp-venmo-gateway',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/Languages/'
		);
	}
}
