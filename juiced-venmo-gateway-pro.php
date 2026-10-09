<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://BrianHenryIE.com
 * @since             1.0.0
 * @package           JuicedPlugins\Venmo_Gateway_Pro
 *
 * @wordpress-plugin
 * Plugin Name:       Venmo Gateway
 * Plugin URI:        http://github.com/JuicedPlugins/venmo-gateway-pro/
 * Description:       Accepts payments via Venmo and reconciles WooCommerce orders through email receipts.
 * Version:           4.3.0
 * Requires PHP:      8.4
 * Requires at least: 6.9
 * Tested up to:      7.1
 * Author:            BrianHenryIE
 * Author URI:        http://BrianHenryIE.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       juiced-venmo-gateway-pro
 * Domain Path:       /languages
 *
 * WC requires at least:   10.1
 * WC tested up to:        11.2
 */

namespace JuicedPlugins\Venmo_Gateway_Pro;

use JuicedPlugins\Venmo_Gateway_Pro\WP_Order_Email_Reconcile\BH_WP_Order_Email_Reconcile;
use JuicedPlugins\Venmo_Gateway_Pro\API\API;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings;
use JuicedPlugins\Venmo_Gateway_Pro\WP_Logger\Logger;
use JuicedPlugins\Venmo_Gateway_Pro\Includes\Activator;
use JuicedPlugins\Venmo_Gateway_Pro\Includes\Deactivator;
use JuicedPlugins\Venmo_Gateway_Pro\Includes\Register_Hooks;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'autoload.php';

/**
 * Current plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'JUICED_VENMO_GATEWAY_PRO_VERSION', '4.3.0' );
define( 'JUICED_VENMO_GATEWAY_PRO_BASENAME', plugin_basename( __FILE__ ) );
define( 'JUICED_VENMO_GATEWAY_PRO_FILE', __FILE__ );

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function instantiate_juiced_venmo_gateway_pro(): API {

	$settings = new Settings();
	$logger   = Logger::instance( $settings );

	$order_email_reconcile = BH_WP_Order_Email_Reconcile::make( $settings, $logger );

	$api = new API( $order_email_reconcile, $settings, $logger );

	new Register_Hooks( $api, $settings, $logger );

	return $api;
}

$GLOBALS['juiced_venmo_gateway_pro'] = instantiate_juiced_venmo_gateway_pro();
