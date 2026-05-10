<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

$GLOBALS['project_root_dir']   = $project_root_dir  = dirname( __DIR__, 1 );
$GLOBALS['plugin_root_dir']    = $plugin_root_dir   = $project_root_dir;
$GLOBALS['plugin_name']        = $plugin_name       = basename( $project_root_dir );
$GLOBALS['plugin_name_php']    = $plugin_name_php   = $plugin_name . '.php';
$GLOBALS['plugin_path_php']    = $plugin_root_dir . '/' . $plugin_name_php;
$GLOBALS['plugin_basename']    = $plugin_name . '/' . $plugin_name_php;
$GLOBALS['wordpress_root_dir'] = $project_root_dir . '/wordpress';



$is_integration_test = array_reduce(
	(array) $_SERVER['argv'],
	fn( $carry, $arg ) => $carry || 'integration' === $arg,
	false
);
if ( $is_integration_test ) {
	global $arbitrary_plugins;
	$arbitrary_plugins = array(
		dirname( __DIR__, 1 ) . '/bh-wp-venmo-gateway.php',
	);
}
