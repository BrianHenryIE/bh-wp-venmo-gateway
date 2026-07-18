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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_donation' ),
					'permission_callback' => '__return_true',
				),
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
	 * Read a donation's legacy status and gateway, for asserting on it after a test
	 * acts on it via the UI (the v3 donations API needs fuller meta than this helper
	 * seeds, so tests assert against the legacy post status instead).
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array{id?:int}> $request -- phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	 */
	public function get_donation( WP_REST_Request $request ): WP_REST_Response {
		$donation_id = (int) $request->get_param( 'id' );

		return new WP_REST_Response(
			array(
				'id'      => $donation_id,
				'status'  => get_post_status( $donation_id ),
				'gateway' => give_get_meta( $donation_id, '_give_payment_gateway', true ),
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
			),
			true
		);

		if ( is_wp_error( $donation_id ) || 0 === $donation_id ) {
			$message = is_wp_error( $donation_id ) ? $donation_id->get_error_message() : 'Failed to insert donation.';
			return new WP_REST_Response( array( 'error' => $message ), 500 );
		}

		// Seed the donation meta the GiveWP v3 Donation model requires to hydrate.
		// The legacy donations list table calls Donation::find() per row, which
		// fatals (Money::fromDecimal(null, null)) without at least amount + currency.
		$meta = array(
			'_give_payment_gateway'          => $gateway,
			'_give_payment_total'            => '25.00',
			'_give_payment_currency'         => 'USD',
			'_give_payment_mode'             => 'live',
			'_give_payment_form_id'          => 0,
			'_give_payment_form_title'       => 'Test Donation Form',
			'_give_payment_donor_id'         => 0,
			'_give_donor_billing_first_name' => 'Test',
			'_give_donor_billing_last_name'  => 'Donor',
			'_give_payment_donor_email'      => 'test-donor@example.com',
		);
		foreach ( $meta as $key => $value ) {
			give_update_meta( $donation_id, $key, $value );
		}

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
