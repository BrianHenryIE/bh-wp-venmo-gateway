<?php
/**
 * Which WordPress capability non-administrators need to work with the plugin's payment emails.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Admin;

use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\WP_Includes\Mailbox_Capabilities;

/**
 * The bh-wp-mailboxes library maps every mailbox capability (`edit_{emails_cpt}`, `manage_{accounts_cpt}`, …) to a single base
 * capability, `manage_options` by default, via its `bh_wp_mailboxes_required_capability` filter. This class answers
 * that filter so the roles that process payments can also read and act on the payment emails:
 *
 * - WooCommerce shop managers (`manage_woocommerce`)
 * - GiveWP managers (`manage_give_settings`)
 *
 * Email account management ("Check now", adding/editing accounts and their credentials) stays with administrators.
 *
 * @see Mailbox_Capabilities::map_meta_cap()
 * @see Unreconciled_Orders_Menu
 */
class Capabilities {

	/**
	 * Capabilities held by the roles that process payments, in order of preference.
	 *
	 * @var string[]
	 */
	const PAYMENT_MANAGER_CAPABILITIES = array( 'manage_woocommerce', 'manage_give_settings' );

	/**
	 * @param Settings_Interface $settings Provides the emails post type name the filter is scoped to.
	 */
	public function __construct(
		protected Settings_Interface $settings,
	) {
	}

	/**
	 * The base capability required to view and process payment emails, and to view unreconciled orders.
	 *
	 * The mailbox filter maps a capability, not a user, so the choice is made for the current user: the first
	 * payment-manager capability they hold, falling back to `manage_options`.
	 */
	public function get_payment_emails_capability(): string {
		foreach ( self::PAYMENT_MANAGER_CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				return $capability;
			}
		}

		return Mailbox_Capabilities::DEFAULT_REQUIRED_CAPABILITY;
	}

	/**
	 * Lower the capability required for the plugin's emails (but not its email accounts).
	 *
	 * @hooked bh_wp_mailboxes_required_capability
	 * @see Mailbox_Capabilities::required_capability()
	 *
	 * @param string $required   The base capability the library requires, `manage_options` by default.
	 * @param string $capability The mailbox capability being checked, e.g. `edit_venmo_payment_emails`.
	 * @param string $post_type  The post type the capability belongs to: the emails or the email accounts CPT.
	 */
	public function filter_required_capability( string $required, string $capability, string $post_type ): string {
		if ( $this->settings->get_emails_cpt_underscored_20() !== $post_type ) {
			return $required;
		}

		return $this->get_payment_emails_capability();
	}
}
