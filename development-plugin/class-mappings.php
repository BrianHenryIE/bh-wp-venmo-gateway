<?php
/**
 * Fixes issues with symlinked directories. I.e. in the project directory, vendor is a sibling of development-plugin
 * rather than a child, which is how it would be in a packaged plugin (e.g. in Playground).
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin;

class Mappings {

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'wp_plugin_paths' ) );
		add_filter( 'plugins_url', array( $this, 'plugins_url_fix' ), 10, 3 );
	}

	public function wp_plugin_paths(): void {

		/**
		 * Fix for mapped directories. I.e. vendor is not under `wp-content/plugins/development-plugins`.
		 *
		 * @see plugin_basename()
		 */
		global $wp_plugin_paths;
		$plugin_path = '/var/www/html/wp-content/uploads/juiced-venmo-gateway-pro/';
		$wp_plugin_paths[ WP_PLUGIN_DIR . '/development-plugin/' ] = $plugin_path;
	}

	/**
	 * Partial fix for symlinks.
	 *
	 * @hooked plugins_url
	 * @see plugins_url()
	 */
	public function plugins_url_fix( string $url, string $_path, string $_plugin ): string {
		$url = str_replace( 'wp-content/plugins/var/www/html/', '', $url );
		$url = str_replace( 'plugins/development-plugin/vendor', 'uploads/juiced-venmo-gateway-pro/vendor', $url );
		$url = str_replace( 'plugins/development-plugin/includes', 'uploads/juiced-venmo-gateway-pro/includes', $url );
		return $url;
	}
}
