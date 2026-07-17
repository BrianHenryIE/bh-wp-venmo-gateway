<?php
/**
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP;

use BrianHenryIE\WP_Venmo_Gateway\Venmo_Username;
use Give\Donations\Models\Donation;
use Give\Framework\PaymentGateways\Commands\PaymentPending;
use Give\Framework\PaymentGateways\PaymentGateway;
use Override;

/**
 * The Venmo payment gateway for GiveWP.
 *
 * Registers Venmo as a GiveWP gateway, records the donor/store @usernames, and creates
 * donations `pending` while they await an off-platform Venmo payment.
 */
class Venmo_Gateway extends PaymentGateway {

	const CUSTOMER_VENMO_USERNAME_META_KEY = '_customer-venmo-username';
	const STORE_VENMO_USERNAME_META_KEY    = '_destination-account-venmo-username';
	const VENMO_TRANSACTION_ID_META_KEY    = '_venmo-transaction-id';
	const VENMO_PAYMENT_DATE_META_KEY      = '_venmo-payment-date';

	/**
	 * @see PaymentGateway::id()
	 */
	#[Override]
	public static function id(): string {
		return 'venmo';
	}

	/**
	 * @see PaymentGateway::getId()
	 */
	#[Override]
	public function getId(): string {
		return self::id();
	}

	/**
	 * @see PaymentGateway::getName()
	 */
	#[Override]
	public function getName(): string {
		return __( 'Venmo', 'bh-wp-venmo-gateway' );
	}

	/**
	 * @see PaymentGateway::getPaymentMethodLabel()
	 */
	#[Override]
	public function getPaymentMethodLabel(): string {
		return __( 'Venmo', 'bh-wp-venmo-gateway' );
	}

	/**
	 * Pass settings to the v3 JS gateway.
	 *
	 * @see PaymentGateway::formSettings()
	 *
	 * @param int $formId The form ID.
	 * @return array<string, string>
	 */
	#[Override]
	public function formSettings( int $formId ): array {
		return array(
			'storeUsername' => give_get_option( 'venmo_store_username', '' ),
		);
	}

	/**
	 * Enqueue JS gateway for GiveWP v3 forms.
	 *
	 * @see PaymentGateway::enqueueScript()
	 *
	 * @param int $formId The form ID.
	 */
	#[Override]
	public function enqueueScript( int $formId ): void {
		$asset_file = plugin_dir_path( BH_WP_VENMO_GATEWAY_FILE ) . 'assets/givewp/venmo-gateway.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'react' ),
				'version'      => BH_WP_VENMO_GATEWAY_VERSION,
			);

		wp_enqueue_script(
			'bh-wp-venmo-gateway-givewp',
			plugins_url( 'assets/givewp/venmo-gateway.js', BH_WP_VENMO_GATEWAY_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Render the Venmo username input for GiveWP v2 (legacy) forms.
	 *
	 * @see LegacyPaymentGatewayInterface::getLegacyFormFieldMarkup()
	 *
	 * @used-by PaymentGateways::getLegacyFormFieldMarkup()
	 * @used-by LegacyPaymentGatewayRegisterAdapter::connectGatewayToLegacyPaymentGatewayAdapter()
	 *
	 * @param int                  $formId The form ID.
	 * @param array<string, mixed> $args   The legacy gateway arguments.
	 */
	public function getLegacyFormFieldMarkup( int $formId, array $args ): string {
		$store_username = give_get_option( 'venmo_store_username', '' );

		ob_start();
		?>
		<fieldset id="give-venmo-gateway-fields" class="give-venmo-gateway-fields">
			<p class="form-row give-venmo-username-row">
				<label class="give-label" for="give-venmo-username">
					<?php esc_html_e( 'Your Venmo @username', 'bh-wp-venmo-gateway' ); ?>
					<span class="give-required-indicator">*</span>
				</label>
				<input
					id="give-venmo-username"
					type="text"
					name="venmo_username"
					class="give-input required"
					placeholder="<?php esc_attr_e( '@username', 'bh-wp-venmo-gateway' ); ?>"
					required
				>
			</p>
			<?php if ( ! empty( $store_username ) ) : ?>
				<p class="give-venmo-instructions">
					<?php
					printf(
						/* translators: %s: Venmo username with @ prefix */
						esc_html__( 'After submitting, please send payment to %s on Venmo.', 'bh-wp-venmo-gateway' ),
						'<strong>@' . esc_html( $store_username ) . '</strong>'
					);
					?>
				</p>
			<?php endif; ?>
		</fieldset>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Create the donation record and mark it pending, awaiting Venmo payment.
	 *
	 * @see PaymentGateway::createPayment()
	 *
	 * @param Donation             $donation    The donation being created.
	 * @param array<string, mixed> $gatewayData Gateway data (v3 forms pass `venmoUsername`).
	 */
	#[Override]
	public function createPayment( Donation $donation, $gatewayData ): PaymentPending {
		// v3 forms pass data via beforeCreatePayment(); v2 via $_POST.
		if ( isset( $gatewayData['venmoUsername'] ) ) {
			$venmo_username = Venmo_Username::sanitize( sanitize_text_field( $gatewayData['venmoUsername'] ) );
		} elseif ( isset( $_POST['venmo_username'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$venmo_username = Venmo_Username::sanitize( sanitize_text_field( wp_unslash( $_POST['venmo_username'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} else {
			$venmo_username = '';
		}

		if ( ! empty( $venmo_username ) ) {
			give_update_meta( $donation->id, self::CUSTOMER_VENMO_USERNAME_META_KEY, $venmo_username );
		}

		$store_username = Venmo_Username::sanitize( give_get_option( 'venmo_store_username', '' ) );
		if ( ! empty( $store_username ) ) {
			give_update_meta( $donation->id, self::STORE_VENMO_USERNAME_META_KEY, $store_username );
		}

		return new PaymentPending();
	}
}
