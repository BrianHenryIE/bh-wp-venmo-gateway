<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * frontend-facing side of the site and the admin area.
 *
 * @package brianhenryie/juiced-venmo-gateway-pro
 */

namespace JuicedPlugins\Venmo_Gateway_Pro\Includes;

use JuicedPlugins\Venmo_Gateway_Pro\Admin\Capabilities;
use JuicedPlugins\Venmo_Gateway_Pro\Admin\Plugins_Page;
use JuicedPlugins\Venmo_Gateway_Pro\Admin\Unreconciled_Orders_Menu;
use JuicedPlugins\Venmo_Gateway_Pro\API\API_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\API\Settings_Interface;
use JuicedPlugins\Venmo_Gateway_Pro\Admin\Admin;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Features;
use JuicedPlugins\Venmo_Gateway_Pro\Psr\Log\LoggerInterface;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Admin_Order_UI;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Email;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Order;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Orders_List_Filter;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Payment_Gateways;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Thank_You;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\GiveWP\Donation_Receipt as GiveWP_Donation_Receipt;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\GiveWP\Donor_Venmo_Profile as GiveWP_Donor_Venmo_Profile;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\GiveWP\Donations_List as GiveWP_Donations_List;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\GiveWP\Gateway_Settings as GiveWP_Gateway_Settings;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\GiveWP\GiveWP;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Venmo_Gateway;
use JuicedPlugins\Venmo_Gateway_Pro\Integrations\WooCommerce\Venmo_Gateway_Blocks_Checkout_Support;

/**
 * `add_action()` and `add_filter()` for the plugin.
 */
class Register_Hooks {

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the frontend-facing side of the site.
	 *
	 * @param API_Interface      $api The core functions (service) of the plugin.
	 * @param Settings_Interface $settings User configurable options for the plugin.
	 * @param LoggerInterface    $logger PSR logger used constucting all objects.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Settings_Interface $settings,
		protected LoggerInterface $logger,
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

		// Let shop managers and GiveWP managers view and process payment emails; accounts remain administrator-only.
		$capabilities = new Capabilities( $this->settings );
		add_filter( 'bh_wp_mailboxes_required_capability', array( $capabilities, 'filter_required_capability' ), 10, 3 );
		add_action( 'admin_init', array( $admin, 'add_setup_notice' ) );

		$plugins_page    = new Plugins_Page();
		$plugin_basename = $this->settings->get_plugin_basename();
		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_settings_action_link' ) );
		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_orders_action_link' ) );
		add_filter( "plugin_action_links_{$plugin_basename}", array( $plugins_page, 'add_unreconciled_orders_action_link' ) );

		// The reconcile library's list of orders/donations still waiting for a payment email, as a hidden admin page linked from plugins.php.
		$unreconciled_orders_menu = new Unreconciled_Orders_Menu( $this->api, $this->settings, $capabilities, $this->logger );
		add_action( 'admin_menu', array( $unreconciled_orders_menu, 'register_submenu' ) );
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

		// The orders list filtered to Venmo orders awaiting payment, linked from the gateway settings page.
		$orders_list_filter = new Orders_List_Filter();
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( $orders_list_filter, 'filter_hpos_list_table_query_args' ) );
		add_filter( 'request', array( $orders_list_filter, 'filter_legacy_list_table_query_vars' ) );

		$admin_order_page = new Order( $this->api, $this->settings, $this->logger );
		// On admin order screen, show the Venmo username in place of the billing address.
		add_filter( 'woocommerce_order_get_formatted_billing_address', array( $admin_order_page, 'admin_view_billing_address' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $admin_order_page, 'schedule_email_check' ), 10, 3 );
		// When a Venmo order is placed, look up the customer's name from their public Venmo profile in the background.
		add_action( 'woocommerce_order_status_changed', array( $admin_order_page, 'schedule_fetch_customer_venmo_profile' ), 10, 3 );
		add_action( Cron::FETCH_CUSTOMER_VENMO_PROFILE_CRON_HOOK, array( $admin_order_page, 'fetch_customer_venmo_profile' ) );

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

		/**
		 * @see wp-admin/plugins.php?plugin_status=incompatible_with_feature
		 */
		$features = new Features( $this->settings );

		/**
		 * Declare compatibility with WooCommerce High Performance Order Storage.
		 */
		add_action( 'before_woocommerce_init', array( $features, 'declare_custom_order_tables_compatibility' ) );

		/**
		 * Declare compatibility with WooCommerce Blocks cart and checkout.
		 */
		add_action( 'before_woocommerce_init', array( $features, 'declare_cart_checkout_blocks_compatibility' ) );
	}

	/**
	 * Register the GiveWP payment gateway and settings.
	 */
	protected function define_givewp_hooks(): void {

		$givewp = new GiveWP();
		add_action( 'givewp_register_payment_gateway', array( $givewp, 'register_gateway' ) );

		// When a Venmo donation is created, look up the donor's name from their public Venmo profile in the background.
		$donor_venmo_profile = new GiveWP_Donor_Venmo_Profile( $this->api, $this->logger );
		add_action( Cron::FETCH_DONOR_VENMO_PROFILE_CRON_HOOK, array( $donor_venmo_profile, 'fetch_donor_venmo_profile' ) );

		$gateway_settings = new GiveWP_Gateway_Settings();
		add_filter( 'give_get_sections_gateways', array( $gateway_settings, 'register_sections' ) );
		add_filter( 'give_get_settings_gateways', array( $gateway_settings, 'register_settings' ) );
		// Store the destination username as the bare handle (no leading "@").
		add_filter( 'give_admin_settings_sanitize_option_venmo_store_username', array( $gateway_settings, 'sanitize_store_username' ) );
		// Sync the log level to the plugin-wide setting shared with the WooCommerce gateway.
		add_filter( 'give_admin_settings_sanitize_option_venmo_log_level', array( $gateway_settings, 'sanitize_log_level' ) );

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
