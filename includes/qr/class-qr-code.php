<?php
/**
 * Generates QR codes as base64-encoded data URIs.
 *
 * Shared by the WooCommerce and GiveWP integrations so the QR rendering options
 * (quiet-zone size, output type) live in one place.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\QR;

use BrianHenryIE\WP_Venmo_Gateway\chillerlan\QRCode\Output\QROutputInterface;
use BrianHenryIE\WP_Venmo_Gateway\chillerlan\QRCode\QRCode;
use BrianHenryIE\WP_Venmo_Gateway\chillerlan\QRCode\QROptions;

/**
 * Wraps the chillerlan/php-qrcode library with the plugin's QR rendering defaults.
 */
class QR_Code {

	/**
	 * Render the given data as a QR code, returned as a base64-encoded data URI
	 * (e.g. `data:image/svg+xml;base64,…`) suitable for an `<img>` `src`.
	 *
	 * @param string $data        The string to encode, e.g. a `venmo://` payment URL.
	 * @param string $output_type One of the {@see QROutputInterface} output-type constants.
	 *                            Defaults to SVG markup; pass `QROutputInterface::GDIMAGE_PNG`
	 *                            for contexts that cannot render inline SVG (e.g. email).
	 */
	public function get_data_uri( string $data, string $output_type = QROutputInterface::MARKUP_SVG ): string {

		$options = new QROptions(
			array(
				'quietzoneSize' => 1,
				'outputType'    => $output_type,
			)
		);

		return ( new QRCode( $options ) )->render( $data );
	}
}
