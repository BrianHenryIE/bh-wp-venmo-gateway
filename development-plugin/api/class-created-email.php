<?php
/**
 * The result of delivering a generated Venmo payment email to the mailbox.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Development_Plugin\API;

/**
 * The stored email's post id and subject.
 */
class Created_Email {

	/**
	 * @param int    $post_id The wp_posts id of the stored email.
	 * @param string $subject The email subject, e.g. "John Doe paid you $46.00".
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $subject,
	) {
	}
}
