<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * frontend-facing side of the site and the admin area.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

namespace BrianHenryIE\WP_Venmo_Gateway\Includes;

use BrianHenryIE\WP_Venmo_Gateway\Admin\Plugins_Page;
use BrianHenryIE\WP_Venmo_Gateway\API\API_Interface;
use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Admin\Admin;
use BrianHenryIE\WP_Venmo_Gateway\Psr\Log\LoggerInterface;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Admin_Order_UI;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Email;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Order;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Payment_Gateways;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Thank_You;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Donation_Receipt as GiveWP_Donation_Receipt;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Donations_List as GiveWP_Donations_List;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\Gateway_Settings as GiveWP_Gateway_Settings;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\GiveWP\GiveWP;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Venmo_Gateway;
use BrianHenryIE\WP_Venmo_Gateway\Integrations\WooCommerce\Venmo_Gateway_Blocks_Checkout_Support;

class Register_Hooks {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the frontend-facing side of the site.
	 *
	 * @param API_Interface      $api
	 * @param Settings_Interface $settings
	 * @param LoggerInterface    $logger
	 */
	public function __construct(
		protected API_Interface $api,
		protected Settings_Interface $settings,
		protected LoggerInterface $logger
	) {
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_woocommerce_hooks();
		$this->define_givewp_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 */
	protected function set_locale(): void {

		$plugin_i18n = new I18n();

		add_action( 'plugins_loaded', array( $plugin_i18n, 'load_plugin_textdomain' ) );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 */
	protected function define_admin_hooks(): void {

		$admin = new Admin();
		add_action( 'plugins_loaded', array( $admin, 'init_notices' ) );
		add_action( 'admin_init', array( $admin, 'add_setup_notice' ) );

		$plugins_page    = new Plugins_Page();
		$plugin_basename = $this->settings->get_plugin_basename();
		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_settings_action_link' ) );
		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_orders_action_link' ) );
	}

	/**
	 * Register the payment gateway and customise the UI.
	 */
	protected function define_woocommerce_hooks(): void {

		$payment_gateways = new Payment_Gateways();
		// Register the payment gateway with WooCommerce.
		add_filter( 'woocommerce_payment_gateways', array( $payment_gateways, 'add_to_woocommerce' ) );

		add_filter( 'woocommerce_order_get_payment_method_title', array( $payment_gateways, 'format_method_title' ), 10, 2 );

		add_filter( 'woocommerce_payment_gateways', array( $payment_gateways, 'filter_to_only_venmo_gateways' ), 100 );

		$admin_order_ui = new Admin_Order_UI();
		add_action( 'add_meta_boxes', array( $admin_order_ui, 'add_venmo_payment_metabox' ) );

		$admin_order_page = new Order( $this->settings, $this->logger );
		// On admin order screen, show the Venmo username in place of the billing address.
		add_filter( 'woocommerce_order_get_formatted_billing_address', array( $admin_order_page, 'admin_view_billing_address' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $admin_order_page, 'schedule_email_check' ), 10, 3 );

		$thank_you = new Thank_You();
		// Display payment instructions on thank you page.
		add_filter( 'woocommerce_thankyou_order_received_text', array( $thank_you, 'print_instructions' ), 10, 2 );

		// Register Venmo gateway with WooCommerce Blocks checkout.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( PaymentMethodRegistry $payment_method_registry ): void {
				$gateways = \WC_Payment_Gateways::instance()->payment_gateways();
				if ( isset( $gateways['venmo'] ) && $gateways['venmo'] instanceof Venmo_Gateway ) {
					$payment_method_registry->register(
						new Venmo_Gateway_Blocks_Checkout_Support( $gateways['venmo'] )
					);
				}
			}
		);

		$email = new Email();
		// Add payment link and instructions to the customer emails.
		add_action( 'woocommerce_email_before_order_table', array( $email, 'email_instructions' ), 10, 2 );
	}

	/**
	 * Register the GiveWP payment gateway and settings.
	 */
	protected function define_givewp_hooks(): void {

		$givewp = new GiveWP();
		add_action( 'givewp_register_payment_gateway', array( $givewp, 'register_gateway' ) );

		$gateway_settings = new GiveWP_Gateway_Settings();
		add_filter( 'give_get_sections_gateways', array( $gateway_settings, 'register_sections' ) );
		add_filter( 'give_get_settings_gateways', array( $gateway_settings, 'register_settings' ) );

		$donation_receipt = new GiveWP_Donation_Receipt();
		// Legacy (v2) confirmation page: replace the generic "currently processing"
		// notice with Venmo payment instructions, and show the QR code.
		add_filter( 'give_receipt_status_notice', array( $donation_receipt, 'customize_pending_notice' ), 10, 4 );
		add_action( 'give_payment_receipt_before_table', array( $donation_receipt, 'print_qr_code' ), 10, 2 );
		// Modern (v3/Sequoia) confirmation receipt: add the same QR code and instructions,
		// and replace the "Success!" badge with a payment link while the donation is pending.
		add_action( 'givewp_generate_confirmation_page_receipt_before_donation_total', array( $donation_receipt, 'add_v3_receipt_details' ) );
		add_action( 'givewp_donation_confirmation_receipt_showing', array( $donation_receipt, 'replace_v3_success_badge' ) );

		$donations_list = new GiveWP_Donations_List();
		// Add a "Mark paid" link to pending Venmo donations in the list table's
		// Status column, opening a modal that records the payment details.
		add_filter( 'give_payments_table_column', array( $donations_list, 'add_mark_paid_link' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $donations_list, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $donations_list, 'render_modal' ) );
		add_action( 'admin_notices', array( $donations_list, 'admin_notice_marked_paid' ) );
		add_action( 'wp_ajax_' . GiveWP_Donations_List::AJAX_ACTION, array( $donations_list, 'ajax_mark_paid' ) );
	}

	/**
	 * Register the cron job.
	 */
	protected function define_cron_hooks(): void {

		$cron = new Cron( $this->api, $this->settings, $this->logger );
		// Make sure the cron job is enabled/disabled as appropriate.
		add_action( 'plugins_loaded', array( $cron, 'add_cron_jon' ) );

		// Hook the function that the cron job will run.
		add_action( Cron::CHECK_FOR_PAYMENT_EMAILS_CRON_HOOK, array( $cron, 'check_for_payment_emails' ) );
	}
}
