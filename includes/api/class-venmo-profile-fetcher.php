<?php
/**
 * Fetch a Venmo user's public profile from venmo.com.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\API;

use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerAwareTrait;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerInterface;
use BrianHenryIE\WP_Venmo_Gateway\Venmo_Username;

/**
 * `https://venmo.com/u/{username}` is a Next.js page whose `__NEXT_DATA__` JSON contains the profile.
 *
 * An unknown username returns HTTP 404.
 */
class Venmo_Profile_Fetcher {
	use LoggerAwareTrait;

	const PROFILE_URL_TEMPLATE = 'https://venmo.com/u/%s';

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->setLogger( $logger );
	}

	/**
	 * The public profile page URL for a username.
	 *
	 * @param string $username The Venmo username, with or without a leading `@`.
	 */
	public function get_profile_url( string $username ): string {
		return sprintf( self::PROFILE_URL_TEMPLATE, rawurlencode( Venmo_Username::sanitize( $username ) ) );
	}

	/**
	 * Fetch the profile, or null when the user does not exist or the page could not be fetched or parsed.
	 *
	 * @param string $username The Venmo username, with or without a leading `@`.
	 */
	public function fetch( string $username ): ?Venmo_Profile {

		$username = Venmo_Username::sanitize( $username );

		if ( '' === $username ) {
			return null;
		}

		$url = $this->get_profile_url( $username );

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->warning(
				'Failed to fetch Venmo profile for @' . $username . ': ' . $response->get_error_message(),
				array(
					'username' => $username,
					'url'      => $url,
					'error'    => $response->get_error_data(),
				)
			);
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 404 === $response_code ) {
			$this->logger->info(
				'No Venmo profile found for @' . $username . '.',
				array(
					'username' => $username,
					'url'      => $url,
				)
			);
			return null;
		}

		if ( 200 !== $response_code ) {
			$this->logger->warning(
				'Unexpected HTTP ' . $response_code . ' fetching Venmo profile for @' . $username . '.',
				array(
					'username'      => $username,
					'url'           => $url,
					'response_code' => $response_code,
				)
			);
			return null;
		}

		$profile = $this->parse_profile_page( wp_remote_retrieve_body( $response ) );

		if ( is_null( $profile ) ) {
			$this->logger->warning(
				'Could not find profile data in the Venmo profile page for @' . $username . '.',
				array(
					'username' => $username,
					'url'      => $url,
				)
			);
		}

		return $profile;
	}

	/**
	 * Extract the profile from the page HTML.
	 *
	 * The page's `<script id="__NEXT_DATA__">` JSON has the user at `props.pageProps.user`:
	 * `{"displayName":"Brian Henry","username":"BrianHenryIE","firstName":"Brian","lastName":"Henry",...}`.
	 *
	 * @param string $html The `https://venmo.com/u/{username}` page.
	 */
	public function parse_profile_page( string $html ): ?Venmo_Profile {

		if ( 1 !== preg_match( '/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches ) ) {
			return null;
		}

		$data = json_decode( $matches[1], true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$user = $data['props']['pageProps']['user'] ?? null;

		if ( ! is_array( $user ) || empty( $user['displayName'] ) || ! is_string( $user['displayName'] ) ) {
			return null;
		}

		$string_or_empty = fn( mixed $value ): string => is_string( $value ) ? trim( $value ) : '';

		return new Venmo_Profile(
			$string_or_empty( $user['username'] ?? '' ),
			trim( $user['displayName'] ),
			$string_or_empty( $user['firstName'] ?? '' ),
			$string_or_empty( $user['lastName'] ?? '' ),
		);
	}
}
