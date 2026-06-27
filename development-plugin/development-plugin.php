<?php
/**
 * Plugin Name:       Venmo Gateway Development Plugin
 * Description:       Convenience, demo and test helper functions.
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-venmo-gateway/
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin;

use BrianHenryIE\WP_Venmo_Gateway\Alley_Interactive\Autoloader\Autoloader;
use BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Admin\WooCommerce;
use BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Admin\WooCommerce_Order;
use BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Rest\Action_Scheduler;
use BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Rest\Themes;
use BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Ajax\WooCommerce_Customer;
use BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Rest\WooCommerce_Settings;

if ( ! defined( 'WPINC' ) ) {
	return;
}

if ( ! is_plugin_active( 'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php' ) ) {
	return;
}

Autoloader::generate(
	'BrianHenryIE\\WP_Venmo_Gateway\\Development_Plugin',
	__DIR__,
)->register();

// `wp-env` symlink mappings fixes.
new Mappings()->register_hooks();

// Authentication helpers.
( new Authentication() )->register_hooks();

// Admin UI changes.
( new WooCommerce() )->register_hooks();
( new WooCommerce_Order() )->register_hooks();

// New REST endpoints.
( new Action_Scheduler() )->register_hooks();
( new Themes() )->register_hooks();
( new WooCommerce_Customer() )->register_hooks();
( new WooCommerce_Settings() )->register_hooks();
