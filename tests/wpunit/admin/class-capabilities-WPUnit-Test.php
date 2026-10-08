<?php
/**
 * Integration tests: with the plugin's capability filter in place, a WooCommerce shop manager holds the
 * mailbox capabilities needed to add and remove the email accounts that are checked for payments.
 *
 * Uses the real Settings (post type names), the real bh-wp-mailboxes post types and its `map_meta_cap`
 * filter, and WooCommerce's real `shop_manager` role.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Admin;

use BrianHenryIE\WP_Venmo_Gateway\API\Settings;
use BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\BH_Email_Account_CPT;
use BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\WP_Includes\BH_Email_CPT;
use BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\WP_Includes\Mailbox_Capabilities;
use BrianHenryIE\WP_Venmo_Gateway\WPUnit_Testcase;
use WP_Post_Type;
use WP_User;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Admin\Capabilities
 */
class Capabilities_WPUnit_Test extends WPUnit_Testcase {

	protected Settings $settings;

	/**
	 * The mailbox capabilities the "Add account" button, the accounts REST routes and the accounts list screen check.
	 *
	 * @see \BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\Admin\Email_Account_Modal::print_add_button()
	 * @see \BrianHenryIE\WP_Venmo_Gateway\WP_Mailboxes\REST\Email_Accounts_REST_Controller::manage_permissions_check()
	 *
	 * @return string[]
	 */
	protected function email_account_capabilities(): array {
		$accounts_post_type = $this->get_post_type( $this->settings->get_email_accounts_cpt_underscored_20() );

		return array(
			( new Mailbox_Capabilities( $this->settings ) )->get_manage_email_accounts_capability(),
			$accounts_post_type->cap->edit_posts,
			$accounts_post_type->cap->delete_posts,
		);
	}

	protected function get_post_type( string $post_type ): WP_Post_Type {
		$post_type_object = get_post_type_object( $post_type );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type_object, "Post type {$post_type} is not registered." );

		return $post_type_object;
	}

	/**
	 * Create a user with the given role and make them the current user.
	 *
	 * @param string $role A registered role.
	 */
	protected function log_in_as( string $role ): void {
		$user_id = wp_create_user( $role . '_' . wp_generate_password( 8, false ), 'password' );
		$this->assertIsInt( $user_id );
		( new WP_User( $user_id ) )->set_role( $role );

		wp_set_current_user( $user_id );
	}

	protected function setUp(): void {
		parent::setUp();

		$this->settings = new Settings();

		( new BH_Email_CPT( $this->settings, $this->logger ) )->register_cpt();
		( new BH_Email_Account_CPT( $this->settings, $this->logger ) )->register_cpt();

		// As wired by bh-wp-mailboxes and by Register_Hooks::define_admin_hooks().
		add_filter( 'map_meta_cap', ( new Mailbox_Capabilities( $this->settings ) )->map_meta_cap( ... ), 10, 4 );
		add_filter( 'bh_wp_mailboxes_required_capability', ( new Capabilities( $this->settings ) )->filter_required_capability( ... ), 10, 3 );
	}

	protected function tearDown(): void {
		unregister_post_type( $this->settings->get_emails_cpt_underscored_20() );
		unregister_post_type( $this->settings->get_email_accounts_cpt_underscored_20() );
		remove_all_filters( 'map_meta_cap' );
		remove_all_filters( 'bh_wp_mailboxes_required_capability' );

		parent::tearDown();
	}

	/**
	 * @covers ::filter_required_capability
	 */
	public function test_shop_manager_can_manage_email_accounts(): void {
		$this->assertContains( 'shop_manager', array_keys( wp_roles()->roles ), 'WooCommerce did not register the shop_manager role.' );

		$this->log_in_as( 'shop_manager' );

		$this->assertFalse( current_user_can( 'manage_options' ), 'A shop manager is not an administrator; the test would prove nothing.' );

		foreach ( $this->email_account_capabilities() as $capability ) {
			$this->assertTrue( current_user_can( $capability ), "Shop manager should have {$capability}." );
		}
	}

	/**
	 * @covers ::filter_required_capability
	 */
	public function test_shop_manager_can_view_and_edit_emails(): void {
		$this->log_in_as( 'shop_manager' );

		$emails_post_type = $this->get_post_type( $this->settings->get_emails_cpt_underscored_20() );

		$this->assertTrue( current_user_can( $emails_post_type->cap->edit_posts ) );
	}

	/**
	 * Roles without `manage_woocommerce` still need to be administrators.
	 *
	 * @covers ::filter_required_capability
	 */
	public function test_editor_cannot_manage_email_accounts(): void {
		$this->log_in_as( 'editor' );

		foreach ( $this->email_account_capabilities() as $capability ) {
			$this->assertFalse( current_user_can( $capability ), "Editor should not have {$capability}." );
		}
	}

	/**
	 * @covers ::filter_required_capability
	 */
	public function test_administrator_can_manage_email_accounts(): void {
		$this->log_in_as( 'administrator' );

		foreach ( $this->email_account_capabilities() as $capability ) {
			$this->assertTrue( current_user_can( $capability ), "Administrator should have {$capability}." );
		}
	}
}
