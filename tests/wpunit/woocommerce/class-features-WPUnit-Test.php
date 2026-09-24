<?php

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\WPUnit_Testcase;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Features
 */
class Features_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @return array<string, array{method:string, feature:string}>
	 */
	public function declare_compatibility_provider(): array {
		return array(
			'custom_order_tables'  => array(
				'method'  => 'declare_custom_order_tables_compatibility',
				'feature' => 'custom_order_tables',
			),
			'cart_checkout_blocks' => array(
				'method'  => 'declare_cart_checkout_blocks_compatibility',
				'feature' => 'cart_checkout_blocks',
			),
		);
	}

	/**
	 * @covers ::declare_custom_order_tables_compatibility
	 * @covers ::declare_cart_checkout_blocks_compatibility
	 * @covers ::__construct
	 *
	 * @dataProvider declare_compatibility_provider
	 *
	 * @param string $method The Features method under test.
	 * @param string $feature The WooCommerce feature id the method declares compatibility with.
	 */
	public function test_declare_compatibility( string $method, string $feature ): void {

		$settings = $this->makeEmpty(
			Settings_Interface::class,
			array(
				'get_plugin_basename' => Expected::once( 'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php' ),
			)
		);

		/**
		 * `doing_action('before_woocommerce_init')` must be true.
		 *
		 * @var string[] $wp_current_filter
		 */
		global $wp_current_filter;
		$wp_current_filter[] = 'before_woocommerce_init';

		wp_cache_init();

		// because the plugin is not in the wp-content/plugins/ directory, we need to mock the plugins cache.
		$cache_plugins = array(
			'' => array(
				'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php' =>
					array(
						'Name' => 'Venmo Gateway',
					),
			),
		);
		wp_cache_set( 'plugins', $cache_plugins, 'plugins', 10 );

		$sut = new Features( $settings );

		$sut->{$method}();

		/** @var array{compatible:array<string>, incompatible:array<string>} $result */
		$result = FeaturesUtil::get_compatible_plugins_for_feature( $feature );

		$this->assertContains( 'bh-wp-venmo-gateway/bh-wp-venmo-gateway.php', $result['compatible'], wp_json_encode( $result['compatible'] ) ?: '' );
	}
}
