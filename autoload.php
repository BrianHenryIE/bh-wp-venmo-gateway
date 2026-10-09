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

// The upgrade classes' file names do not follow the Alley_Interactive autoloader's convention.
require_once __DIR__ . '/upgrades/class-v4-3-0.php';
