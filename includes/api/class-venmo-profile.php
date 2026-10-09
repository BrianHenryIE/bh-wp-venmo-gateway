<?php
/**
 * A Venmo user's public profile, as shown at `https://venmo.com/u/{username}`.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\API;

/**
 * The subset of the public profile the plugin uses.
 *
 * The display name is what appears in Venmo's "{name} paid you" emails, so it can be used to match
 * a payment email to the order whose customer entered only their username at checkout.
 */
class Venmo_Profile {

	/**
	 * Constructor.
	 *
	 * @param string $username     The Venmo username, in the case Venmo returns it.
	 * @param string $display_name The user's full name as shown on Venmo, e.g. "Brian Henry".
	 * @param string $first_name   The user's first name.
	 * @param string $last_name    The user's last name.
	 */
	public function __construct(
		public readonly string $username,
		public readonly string $display_name,
		public readonly string $first_name,
		public readonly string $last_name,
	) {
	}
}
