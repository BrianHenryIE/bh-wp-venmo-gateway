<?php
/**
 * Registers hooks for the GiveWP integration.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use Give\Framework\PaymentGateways\PaymentGatewayRegister;
use Give\PaymentGateways\Actions\RegisterPaymentGateways;

/**
 * Registers the Venmo payment gateway with GiveWP.
 */
class GiveWP {

	/**
	 * Register the Venmo payment gateway with GiveWP.
	 *
	 * @hooked givewp_register_payment_gateway
	 * @see RegisterPaymentGateways::register3rdPartyPaymentGateways()
	 *
	 * @param PaymentGatewayRegister $register GiveWP's gateway registrar.
	 */
	public function register_gateway( PaymentGatewayRegister $register ): void {
		$register->registerGateway( Venmo_Gateway::class );
	}
}
