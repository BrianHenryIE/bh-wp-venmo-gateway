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
 * @package           BrianHenryIE\WP_Venmo_Gateway
 *
 * @wordpress-plugin
 * Plugin Name:       Venmo Gateway
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wc-venmo-gateway/
 * Description:       Accepts payments via Venmo and reconciles WooCommerce orders through email receipts.
 * Version:           3.2.0
 * Requires PHP:      8.0
 * Author:            BrianHenryIE
 * Author URI:        http://BrianHenryIE.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-wc-venmo-gateway
 * Domain Path:       /languages
 */

namespace BrianHenryIE\WP_Venmo_Gateway;

use BrianHenryIE\WP_Venmo_Gateway\WP_Order_Email_Reconcile\BH_WP_Order_Email_Reconcile;
use BrianHenryIE\WP_Venmo_Gateway\API\API;
use BrianHenryIE\WP_Venmo_Gateway\API\Settings;
use BrianHenryIE\WP_Venmo_Gateway\WP_Logger\Logger;
use BrianHenryIE\WP_Venmo_Gateway\Includes\Activator;
use BrianHenryIE\WP_Venmo_Gateway\Includes\Deactivator;
use BrianHenryIE\WP_Venmo_Gateway\Includes\Register_Hooks;

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
define( 'BH_WP_VENMO_GATEWAY_VERSION', '3.2.0' );
define( 'BH_WP_VENMO_GATEWAY_BASENAME', plugin_basename( __FILE__ ) );
define( 'BH_WP_VENMO_GATEWAY_FILE', __FILE__ );

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
function instantiate_bh_wp_venmo_gateway(): API {

	$settings = new Settings();
	$logger   = Logger::instance( $settings );

	$order_email_reconcile = BH_WP_Order_Email_Reconcile::make( $settings, $logger );

	$api = new API( $order_email_reconcile, $settings, $logger );

	$plugin = new Register_Hooks( $api, $settings, $logger );

	return $api;
}

/** @var API $GLOBALS['bh_wp_venmo_gateway'] */
$GLOBALS['bh_wp_venmo_gateway'] = instantiate_bh_wp_venmo_gateway();
