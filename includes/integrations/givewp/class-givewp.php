<?php
/**
 * Registers hooks for the GiveWP integration.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use Give\Framework\PaymentGateways\PaymentGatewayRegister;
use Give\PaymentGateways\Actions\RegisterPaymentGateways;

class GiveWP {

	/**
	 * Register the Venmo payment gateway with GiveWP.
	 *
	 * @hooked givewp_register_payment_gateway
	 * @see RegisterPaymentGateways::register3rdPartyPaymentGateways()
	 *
	 * @param PaymentGatewayRegister $register
	 */
	public function register_gateway( PaymentGatewayRegister $register ): void {
		$register->registerGateway( Venmo_Gateway::class );
	}
}
