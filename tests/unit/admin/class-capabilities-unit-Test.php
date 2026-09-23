<?php
/**
 * Unit tests for the capability mapping that lets shop managers and GiveWP managers process payment emails and manage email accounts.
 *
 * @package brianhenryie/bh-wp-venmo-gateway
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Venmo_Gateway\Admin;

use BrianHenryIE\WP_Venmo_Gateway\API\Settings_Interface;
use BrianHenryIE\WP_Venmo_Gateway\Unit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Venmo_Gateway\Admin\Capabilities
 */
class Capabilities_Unit_Test extends Unit_Testcase {

	/**
	 * Mock `current_user_can()` to hold exactly the given capabilities.
	 *
	 * @param string[] $held The capabilities the current user has.
	 */
	private function user_has( array $held ): void {
		\WP_Mock::userFunction( 'current_user_can' )->andReturnUsing( fn( string $cap ) => in_array( $cap, $held, true ) );
	}

	private function sut(): Capabilities {
		return new Capabilities(
			$this->makeEmpty(
				Settings_Interface::class,
				array(
					'get_emails_cpt_underscored_20' => 'venmo_payment_emails',
					'get_email_accounts_cpt_underscored_20' => 'venmo_email_accounts',
				)
			)
		);
	}

	/**
	 * @covers ::get_payment_emails_capability
	 */
	public function test_shop_manager_uses_manage_woocommerce(): void {
		$this->user_has( array( 'manage_woocommerce', 'edit_shop_orders' ) );

		$this->assertSame( 'manage_woocommerce', $this->sut()->get_payment_emails_capability() );
	}

	/**
	 * @covers ::get_payment_emails_capability
	 */
	public function test_give_manager_uses_manage_give_settings(): void {
		$this->user_has( array( 'manage_give_settings', 'view_give_payments' ) );

		$this->assertSame( 'manage_give_settings', $this->sut()->get_payment_emails_capability() );
	}

	/**
	 * @covers ::get_payment_emails_capability
	 */
	public function test_other_users_require_manage_options(): void {
		$this->user_has( array( 'edit_posts', 'edit_others_posts' ) );

		$this->assertSame( 'manage_options', $this->sut()->get_payment_emails_capability() );
	}

	/**
	 * @covers ::filter_required_capability
	 */
	public function test_filter_lowers_capability_for_emails_post_type(): void {
		$this->user_has( array( 'manage_woocommerce' ) );

		$this->assertSame( 'manage_woocommerce', $this->sut()->filter_required_capability( 'manage_options', 'edit_venmo_payment_emails', 'venmo_payment_emails' ) );
	}

	/**
	 * Shop managers add and remove the email accounts that are checked for payments.
	 *
	 * @covers ::filter_required_capability
	 */
	public function test_filter_lowers_capability_for_email_accounts_post_type(): void {
		$this->user_has( array( 'manage_woocommerce' ) );

		$this->assertSame( 'manage_woocommerce', $this->sut()->filter_required_capability( 'manage_options', 'manage_venmo_email_accounts', 'venmo_email_accounts' ) );
	}

	/**
	 * The filter is shared by every mailbox on the site; another plugin's mailbox is left alone.
	 *
	 * @covers ::filter_required_capability
	 */
	public function test_filter_leaves_other_mailboxes_alone(): void {
		$this->user_has( array( 'manage_woocommerce' ) );

		$this->assertSame( 'manage_options', $this->sut()->filter_required_capability( 'manage_options', 'manage_other_email_accounts', 'other_email_accounts' ) );
	}
}
