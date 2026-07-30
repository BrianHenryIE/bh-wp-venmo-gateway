<?php
/**
 * Test double used to intercept wp_send_json_error()/wp_send_json_success(),
 * which terminate the request in production. Kept in its own (non-*Test.php)
 * file so Codeception's test loader does not scan it as a test case.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

/**
 * Carries the wp_send_json_* outcome so a test can assert which branch ran.
 */
class Json_Response_Exception extends \Exception {

	public bool $success;
	/** @var mixed */
	public $data;
	public ?int $status;

	/**
	 * @param bool     $success Whether this represents a success response.
	 * @param mixed    $data    The response payload passed to wp_send_json_*.
	 * @param int|null $status  The HTTP status code, if one was given.
	 */
	public function __construct( bool $success, $data, ?int $status ) {
		$this->success = $success;
		$this->data    = $data;
		$this->status  = $status;
		parent::__construct( $success ? 'success' : 'error' );
	}
}
