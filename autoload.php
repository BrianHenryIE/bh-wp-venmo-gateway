<?php
/**
 * Loads all required classes
 *
 * Uses classmap, PSR4 & Alley_Interactive autoloader
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro;

use JuicedPlugins\Venmo_Gateway_Pro\Alley_Interactive\Autoloader\Autoloader;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Load strauss classes after autoload-classmap.php so classes can be substituted.
require_once __DIR__ . '/vendor-prefixed/autoload.php';

Autoloader::generate(
	'JuicedPlugins\Venmo_Gateway_Pro',
	__DIR__ . '/includes',
)->register();
