<?php
/**
 * Plugin Name:       Venmo Gateway Development Plugin
 * Description:       Convenience, demo and test helper functions.
 * Plugin URI:        http://github.com/BrianHenryIE/juiced-venmo-gateway-pro/
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin;

use JuicedPlugins\Venmo_Gateway_Pro\Alley_Interactive\Autoloader\Autoloader;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Admin\Reconciliation_Email_Metabox;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Admin\WooCommerce;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Admin\WooCommerce_Order;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest\Action_Scheduler;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest\Give_Donations;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest\Themes;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest\Venmo_Profiles;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Ajax\WooCommerce_Customer;
use JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest\WooCommerce_Settings;
use Give\Helpers\Hooks;
use Give\Onboarding\Wizard\Page;
use Give\Onboarding\Wizard\Page as WizardPage;

if ( ! defined( 'WPINC' ) ) {
	return;
}

if ( ! is_plugin_active( 'juiced-venmo-gateway-pro/juiced-venmo-gateway-pro.php' ) ) {
	return;
}

// Plugins load alphabetically, so the main plugin's autoloader has not run yet.
require_once WP_PLUGIN_DIR . '/juiced-venmo-gateway-pro/autoload.php';

Autoloader::generate(
	'JuicedPlugins\\Venmo_Gateway_Pro\\Development_Plugin',
	__DIR__,
)->register();

// `wp-env` symlink mappings fixes.
new Mappings()->register_hooks();

// Disable first-run wizards.
new First_Run_Wizards()->register_hooks();

// Authentication helpers.
( new Authentication() )->register_hooks();

// Admin UI changes.
( new WooCommerce() )->register_hooks();
( new WooCommerce_Order() )->register_hooks();
( new Reconciliation_Email_Metabox() )->register_hooks();

// New REST endpoints.
( new Action_Scheduler() )->register_hooks();
( new Give_Donations() )->register_hooks();
( new Themes() )->register_hooks();
( new Venmo_Profiles() )->register_hooks();
( new WooCommerce_Customer() )->register_hooks();
( new WooCommerce_Settings() )->register_hooks();
