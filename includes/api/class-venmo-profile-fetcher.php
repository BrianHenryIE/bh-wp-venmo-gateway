<?php
/**
 * Fetch a Venmo user's public profile from venmo.com.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\API;

use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerAwareTrait;
use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerInterface;
use JuicedPlugins\Venmo_Gateway_Pro\Venmo_Username;
use Closure;
use WP_Error;

/**
 * `https://venmo.com/u/{username}` is a Next.js page whose `__NEXT_DATA__` JSON contains the profile.
 *
 * An unknown username returns HTTP 404.
 *
 * HTTP 429 and 5xx responses are retried, honouring `Retry-After` when present. Profiles are fetched in
 * background cron jobs, so blocking briefly between attempts is acceptable.
 */
class Venmo_Profile_Fetcher {
	use LoggerAwareTrait;

	const PROFILE_URL_TEMPLATE = 'https://venmo.com/u/%s';

	/**
	 * Total number of requests made before giving up on a 429/5xx response.
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Upper bound on any single wait, so a large `Retry-After` cannot stall the cron job.
	 */
	const MAX_RETRY_DELAY_SECONDS = 10;

	/**
	 * Waits the given number of seconds between attempts.
	 *
	 * @var Closure(int):void
	 */
	protected Closure $sleep;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger PSR logger.
	 * @param ?Closure        $sleep  Waits between retries; defaults to {@see sleep()}. Replaced in tests.
	 */
	public function __construct( LoggerInterface $logger, ?Closure $sleep = null ) {
		$this->setLogger( $logger );
		$this->sleep = $sleep ?? static function ( int $seconds ): void {
			sleep( $seconds );
		};
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

		$response = $this->request_with_retries( $url, $username );

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
	 * GET the URL, retrying HTTP 429 and 5xx responses up to {@see self::MAX_ATTEMPTS} times in total.
	 *
	 * The last response is returned whether or not it succeeded, for {@see self::fetch()} to handle.
	 *
	 * @param string $url      The profile page URL.
	 * @param string $username The sanitized username, for logging.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	protected function request_with_retries( string $url, string $username ): array|WP_Error {

		for ( $attempt = 1; ; $attempt++ ) {

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) || $attempt >= self::MAX_ATTEMPTS ) {
				return $response;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( ! $this->is_retryable_response_code( $response_code ) ) {
				return $response;
			}

			$delay = $this->get_retry_delay( $response, $attempt );

			$this->logger->debug(
				'HTTP ' . $response_code . ' fetching Venmo profile for @' . $username . ', retrying in ' . $delay . 's (attempt ' . $attempt . ' of ' . self::MAX_ATTEMPTS . ').',
				array(
					'username'      => $username,
					'url'           => $url,
					'response_code' => $response_code,
					'attempt'       => $attempt,
					'delay'         => $delay,
				)
			);

			( $this->sleep )( $delay );
		}
	}

	/**
	 * Rate limited or a server error, i.e. a response that may succeed if tried again.
	 *
	 * @param int|string $response_code The HTTP status code, or '' when the response had none.
	 */
	protected function is_retryable_response_code( int|string $response_code ): bool {
		return 429 === $response_code || ( is_int( $response_code ) && $response_code >= 500 && $response_code <= 599 );
	}

	/**
	 * Seconds to wait before the next attempt: the response's `Retry-After` (in seconds) if given, otherwise
	 * exponential backoff (1s, 2s, 4s…), capped at {@see self::MAX_RETRY_DELAY_SECONDS}.
	 *
	 * An HTTP-date `Retry-After` is ignored in favour of the backoff.
	 *
	 * @param array<string,mixed> $response The HTTP response.
	 * @param int                 $attempt  The attempt that just failed, starting at 1.
	 */
	protected function get_retry_delay( array $response, int $attempt ): int {

		$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );

		$delay = is_string( $retry_after ) && ctype_digit( trim( $retry_after ) )
			? (int) trim( $retry_after )
			: 1 << ( $attempt - 1 );

		return min( $delay, self::MAX_RETRY_DELAY_SECONDS );
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
