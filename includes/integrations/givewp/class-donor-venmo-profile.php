<?php
/**
 * Background task: record the donor's full name from their public Venmo profile on the donation.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Integrations\GiveWP;

use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\Includes\Cron;
use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerAwareTrait;
use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerInterface;

/**
 * The donor enters only their Venmo username on the form, but Venmo's payment emails name the payer, so the
 * profile name is what a payment email can later be matched against.
 *
 * @see Venmo_Gateway::createPayment() schedules the event.
 */
class Donor_Venmo_Profile {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_Interface   $api    The plugin's API, to fetch Venmo profiles.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Fetch the donor's public Venmo profile and record their full name in the donation meta, with a note.
	 *
	 * @hooked juiced_venmo_gateway_pro_fetch_donor_venmo_profile
	 * @see Cron::FETCH_DONOR_VENMO_PROFILE_CRON_HOOK
	 *
	 * @param int $donation_id The donation (give_payment post) id.
	 */
	public function fetch_donor_venmo_profile( int $donation_id ): void {

		$username = give_get_meta( $donation_id, Venmo_Gateway::CUSTOMER_VENMO_USERNAME_META_KEY, true );

		if ( ! is_string( $username ) || '' === $username ) {
			$this->logger->debug( 'Donation ' . $donation_id . ' has no donor Venmo username.', array( 'donation_id' => $donation_id ) );
			return;
		}

		$profile = $this->api->get_venmo_profile( $username );

		if ( is_null( $profile ) ) {
			give_insert_payment_note(
				$donation_id,
				sprintf(
					/* translators: %s: the donor's Venmo username. */
					__( 'Could not find a Venmo profile for @%s.', 'juiced-venmo-gateway-pro' ),
					$username
				)
			);
			$this->logger->info(
				'No Venmo profile found for @' . $username . ' (donation ' . $donation_id . ').',
				array(
					'donation_id' => $donation_id,
					'username'    => $username,
				)
			);
			return;
		}

		give_update_meta( $donation_id, Venmo_Gateway::CUSTOMER_VENMO_DISPLAY_NAME_META_KEY, $profile->display_name );
		give_insert_payment_note(
			$donation_id,
			sprintf(
				/* translators: 1: the donor's Venmo username, 2: the donor's name on their Venmo profile. */
				__( 'Venmo profile @%1$s is %2$s.', 'juiced-venmo-gateway-pro' ),
				$username,
				$profile->display_name
			)
		);

		$this->logger->info(
			'Recorded Venmo profile name "' . $profile->display_name . '" for @' . $username . ' on donation ' . $donation_id . '.',
			array(
				'donation_id'  => $donation_id,
				'username'     => $username,
				'display_name' => $profile->display_name,
			)
		);
	}
}
