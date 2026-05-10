<?php
/**
 * Loads all required classes
 *
 * Uses classmap, PSR4 & Alley_Interactive autoloader
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway;

use BrianHenryIE\WP_Venmo_Gateway\Alley_Interactive\Autoloader\Autoloader;

// Load strauss classes after autoload-classmap.php so classes can be substituted.
require_once __DIR__ . '/vendor-prefixed/autoload.php';

Autoloader::generate(
	'BrianHenryIE\WP_Venmo_Gateway',
	__DIR__ . '/includes',
)->register();
