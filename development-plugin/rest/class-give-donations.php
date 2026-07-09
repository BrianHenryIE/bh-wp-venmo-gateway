<?php
/**
 * Test-helper REST endpoint to create and delete GiveWP donations with a
 * specific status and date, for arranging E2E tests.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * POST/DELETE /wp-json/e2e-test-helper/v1/give/donation
 */
class Give_Donations {

	/**
	 * Add hooks to register the REST endpoints.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the create/delete donation routes.
	 *
	 * @hooked rest_api_init
	 */
	public function register_routes(): void {
		register_rest_route(
			'e2e-test-helper/v1',
			'/give/donation',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_donation' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_donation' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Create a minimal donation with the given gateway, status and date.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array{status?:string,date?:string,gateway?:string}> $request -- phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function create_donation( WP_REST_Request $request ): WP_REST_Response {
		$status  = (string) ( $request->get_param( 'status' ) ?: 'pending' );
		$date    = (string) ( $request->get_param( 'date' ) ?: current_time( 'mysql' ) );
		$gateway = (string) ( $request->get_param( 'gateway' ) ?: 'venmo' );

		$donation_id = wp_insert_post(
			array(
				'post_type'     => 'give_payment',
				'post_status'   => $status,
				'post_date'     => $date,
				'post_date_gmt' => get_gmt_from_date( $date ),
			)
		);

		give_update_meta( $donation_id, '_give_payment_gateway', $gateway );

		return new WP_REST_Response(
			array(
				'id'     => $donation_id,
				'status' => get_post_status( $donation_id ),
				'date'   => get_post_field( 'post_date', $donation_id ),
			)
		);
	}

	/**
	 * Permanently delete a donation created for a test.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array{id?:int}> $request -- phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function delete_donation( WP_REST_Request $request ): WP_REST_Response {
		$donation_id = (int) $request->get_param( 'id' );
		wp_delete_post( $donation_id, true );

		return new WP_REST_Response( array( 'deleted' => $donation_id ) );
	}
}
