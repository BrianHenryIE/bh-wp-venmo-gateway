<?php
/**
 * Mock `https://venmo.com/u/{username}` for tests.
 *
 * `POST /wp-json/e2e-test-helper/v1/venmo-profiles` `{ "username": "brianhenryie", "display_name": "Brian Henry" }`
 * records a fake profile; `DELETE .../venmo-profiles/{username}` removes it; `DELETE .../venmo-profiles` clears all.
 * While a username is recorded, HTTP requests for its profile page are short-circuited with a page in the shape
 * venmo.com serves, so tests do not depend on the network or on real Venmo accounts. Other usernames pass through.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

declare(strict_types=1);

namespace JuicedPlugins\Venmo_Gateway_Pro\Development_Plugin\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Recorded profiles are stored in an option so they apply across requests (checkout, cron).
 */
class Venmo_Profiles {

	const OPTION_NAME = 'juiced_venmo_gateway_pro_dev_venmo_profiles';

	/**
	 * Add hooks to register the REST endpoints and the HTTP short-circuit.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'pre_http_request', array( $this, 'mock_venmo_profile_page' ), 10, 3 );
	}

	/**
	 * @hooked rest_api_init
	 */
	public function register_routes(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/venmo-profiles',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_profile' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'username'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'display_name' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => fn() => new WP_REST_Response( $this->get_profiles(), 200 ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_all_profiles' ),
					'permission_callback' => '__return_true',
				),
			)
		);
		register_rest_route(
			'e2e-test-helper/v1',
			'/venmo-profiles/(?P<username>[A-Za-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_profile' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Record a fake profile.
	 *
	 * @param WP_REST_Request $request With `username` and `display_name` params.
	 */
	public function add_profile( WP_REST_Request $request ): WP_REST_Response {
		$profiles = $this->get_profiles();

		$username = strtolower( ltrim( (string) $request->get_param( 'username' ), '@' ) );

		$profiles[ $username ] = (string) $request->get_param( 'display_name' );

		update_option( self::OPTION_NAME, $profiles, false );

		return new WP_REST_Response( $profiles, 201 );
	}

	/**
	 * Remove one fake profile.
	 *
	 * @param WP_REST_Request $request With the `username` route param.
	 */
	public function delete_profile( WP_REST_Request $request ): WP_REST_Response {
		$profiles = $this->get_profiles();

		unset( $profiles[ strtolower( (string) $request->get_param( 'username' ) ) ] );

		update_option( self::OPTION_NAME, $profiles, false );

		return new WP_REST_Response( $profiles, 200 );
	}

	/**
	 * Remove all fake profiles.
	 */
	public function delete_all_profiles(): WP_REST_Response {
		delete_option( self::OPTION_NAME );

		return new WP_REST_Response( array(), 200 );
	}

	/**
	 * Serve a fake profile page for recorded usernames.
	 *
	 * @hooked pre_http_request
	 * @see WP_Http::request()
	 *
	 * @param false|array<string,mixed>|\WP_Error $response    Short-circuit value; false to continue with the real request.
	 * @param array<string,mixed>                 $parsed_args The request arguments.
	 * @param string                              $url         The request URL.
	 *
	 * @return false|array<string,mixed>|\WP_Error
	 */
	public function mock_venmo_profile_page( $response, array $parsed_args, string $url ) {
		if ( 1 !== preg_match( '#^https://venmo\.com/u/([^/?\#]+)#', $url, $matches ) ) {
			return $response;
		}

		$username = strtolower( rawurldecode( $matches[1] ) );
		$profiles = $this->get_profiles();

		if ( ! isset( $profiles[ $username ] ) ) {
			return $response;
		}

		$display_name = $profiles[ $username ];
		$name_parts   = explode( ' ', $display_name, 2 );

		$next_data = array(
			'props' => array(
				'pageProps' => array(
					'user'     => array(
						'displayName' => $display_name,
						'id'          => (string) crc32( $username ),
						'username'    => $matches[1],
						'firstName'   => $name_parts[0],
						'lastName'    => $name_parts[1] ?? '',
					),
					'pageType' => 'profile',
				),
			),
			'page'  => '/u/[username]',
		);

		$body = sprintf(
			'<!DOCTYPE html><html lang="en"><head><title>Venmo</title></head><body><div id="__next"></div><script id="__NEXT_DATA__" type="application/json">%s</script></body></html>',
			wp_json_encode( $next_data )
		);

		return array(
			'headers'  => array( 'content-type' => 'text/html; charset=utf-8' ),
			'body'     => $body,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * @return array<string, string> Lower-cased username => display name.
	 */
	protected function get_profiles(): array {
		$profiles = get_option( self::OPTION_NAME, array() );
		return is_array( $profiles ) ? $profiles : array();
	}
}
