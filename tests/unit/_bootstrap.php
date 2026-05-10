<?php
/**
 * PHPUnit bootstrap file for WP_Mock.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();

global $plugin_root_dir;
require_once $plugin_root_dir . '/autoload.php';

if ( ! defined( 'WC_ABSPATH' ) ) {
	define( 'WC_ABSPATH', codecept_root_dir( 'wp-content/plugins/woocommerce/' ) );
}


$class_map = array(
	'WC_Order'            => 'wp-content/plugins/woocommerce/includes/class-wc-order.php',
	'WC_Abstract_Order'   => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-order.php',
	'WC_Data'             => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-data.php',
	'WC_Item_Totals'      => 'wp-content/plugins/woocommerce/includes/traits/trait-wc-item-totals.php',
	'Automattic\\WooCommerce\\Internal\\CostOfGoodsSold\\CogsAwareTrait' => 'wp-content/plugins/woocommerce/src/Internal/CostOfGoodsSold/CogsAwareTrait.php',
	'WC_Payment_Gateway'  => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php',
	'WC_Payment_Gateways' => 'wp-content/plugins/woocommerce/includes/class-wc-payment-gateways.php',
	'WC_Settings_API'     => 'wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-settings-api.php',
	'Automattic\\WooCommerce\\Blocks\\Integrations\\IntegrationInterface' => 'wp-content/plugins/woocommerce/src/Blocks/Integrations/IntegrationInterface.php',
	'Automattic\\WooCommerce\\Blocks\\Payments\\PaymentMethodTypeInterface' => 'wp-content/plugins/woocommerce/src/Blocks/Payments/PaymentMethodTypeInterface.php',
	'Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' => 'wp-content/plugins/woocommerce/src/Blocks/Payments/Integrations/AbstractPaymentMethodType.php',
);

spl_autoload_register(
	function ( $classname ) use ( $class_map ) {

		if ( array_key_exists( $classname, $class_map ) && file_exists( codecept_root_dir( $class_map[ $classname ] ) ) ) {
			require_once codecept_root_dir( $class_map[ $classname ] );
		}
	}
);
