<?php
/**
 * Normalises Venmo usernames.
 *
 * Venmo usernames and `venmo.com/{username}` URLs use the bare handle, so the
 * value is always stored WITHOUT a leading "@" and formatted WITH one for display.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway;

/**
 * Static helpers to strip the leading "@" when saving and add it when displaying.
 */
class Venmo_Username {

	/**
	 * The value to persist: trimmed, with any leading "@" removed.
	 *
	 * @param string $username The raw username, possibly with a leading "@".
	 */
	public static function sanitize( string $username ): string {
		return ltrim( trim( $username ), '@' );
	}

	/**
	 * The value to display: exactly one leading "@", or an empty string when there is no username.
	 *
	 * @param string $username The stored (or raw) username.
	 */
	public static function for_display( string $username ): string {
		$username = self::sanitize( $username );
		return '' === $username ? '' : '@' . $username;
	}
}
