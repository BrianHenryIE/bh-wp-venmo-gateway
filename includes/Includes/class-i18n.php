<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/includes
 */

namespace BrianHenryIE\WC_Venmo_Gateway\Includes;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    BrianHenryIE\WC_Venmo_Gateway
 * @subpackage BrianHenryIE\WC_Venmo_Gateway/includes
 * @author     Brian Henry <BrianHenryIE@gmail.com>
 */
class I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain(): void {

		load_plugin_textdomain(
			'bh-wc-venmo-gateway',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/Languages/'
		);
	}
}
