<?php
/**
 * When WooCommerce's FeaturesUtil class is unavailable, the compatibility declarations should return early.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;
use Mockery;
use Mockery\MockInterface;

use function Patchwork\redefine;
use function Patchwork\relay;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Features
 */
class Features_Unit_Test extends Unit_Testcase {

	/**
	 * @return array<string, array{method:string}>
	 */
	public function declare_compatibility_provider(): array {
		return array(
			'custom_order_tables'  => array(
				'method' => 'declare_custom_order_tables_compatibility',
			),
			'cart_checkout_blocks' => array(
				'method' => 'declare_cart_checkout_blocks_compatibility',
			),
		);
	}

	/**
	 * Without FeaturesUtil (e.g. an old WooCommerce, or WooCommerce not active), nothing should be declared
	 * and the settings should not even be queried for the plugin basename.
	 *
	 * @covers ::declare_custom_order_tables_compatibility
	 * @covers ::declare_cart_checkout_blocks_compatibility
	 *
	 * @dataProvider declare_compatibility_provider
	 *
	 * @param string $method The Features method under test.
	 */
	public function test_returns_early_when_features_util_class_does_not_exist( string $method ): void {

		redefine(
			'class_exists',
			function ( string $class_name ) {
				return FeaturesUtil::class === $class_name
					? false
					: relay( func_get_args() );
			}
		);

		/** @var Settings_Interface&MockInterface $settings */
		$settings = Mockery::mock( Settings_Interface::class );
		$settings->shouldNotReceive( 'get_plugin_basename' );

		$sut = new Features( $settings );

		$sut->{$method}();

		$this->assertFalse( class_exists( FeaturesUtil::class ), 'Sanity check: class_exists should have been redefined.' );
	}
}
