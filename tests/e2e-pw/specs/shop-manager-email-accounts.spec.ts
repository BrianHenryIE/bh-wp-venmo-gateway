/**
 * Playwright E2E test: a WooCommerce shop manager can add and remove the email accounts that are checked
 * for payment emails.
 *
 * The bh-wp-mailboxes library requires `manage_options` for account management by default; the plugin's
 * `Capabilities` class lowers that to `manage_woocommerce` so shop managers can manage the accounts.
 *
 * Arrange: nothing (the `shopmanager` user is created in tests/_wp-env/initialize-internal.sh).
 * Act: log in as the shop manager, add an IMAP account through the "Add account" modal on the emails list
 * screen, then delete it through the account row's "Delete" action.
 * Assert: via the UI, since the accounts table is what the shop manager uses; the REST routes behind it
 * share the same capability check.
 *
 * The IMAP server is a closed local port, so the connection test the save runs fails instantly; the
 * account is saved regardless, with a warning.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { login, logout } from '../helpers/general/ui/login';
import { testConfig } from '../test-config';

const EMAILS_LIST = '/wp-admin/edit.php?post_type=venmo_payment_emails';

test.describe( 'WooCommerce shop manager managing email accounts', () => {
	test( 'can add and remove an IMAP account', async ( { page } ) => {
		const emailAddress = `shop-manager-${ Date.now() }@example.org`;

		await logout( page );
		await login( testConfig.users.shopManager, page );

		await page.goto( EMAILS_LIST, { waitUntil: 'domcontentloaded' } );

		// Add.
		await page.locator( '.bh-account-add' ).click();
		const dialog = page.locator( '#bh-mailboxes-account-dialog' );
		await expect( dialog ).toBeVisible();
		await dialog
			.locator( '#bh-mailboxes-account-email-address' )
			.fill( emailAddress );
		await dialog
			.locator( '#bh-mailboxes-account-display-name' )
			.fill( 'Shop Manager Test Account' );
		await dialog
			.locator( '#bh-mailboxes-account-server' )
			.fill( '127.0.0.1:1' );
		await dialog
			.locator( '#bh-mailboxes-account-username' )
			.fill( emailAddress );
		await dialog
			.locator( '#bh-mailboxes-account-password' )
			.fill( 'password' );
		await dialog.locator( '.bh-mailboxes-account-form__submit' ).click();

		await expect( dialog ).toBeHidden();
		const row = page.locator(
			`.bh-mailboxes-accounts__rows tr[data-email-address="${ emailAddress }"]`
		);
		await expect( row ).toBeVisible();

		// The account survives a reload, i.e. it was saved, not just rendered.
		await page.reload( { waitUntil: 'domcontentloaded' } );
		await expect( row ).toBeVisible();

		// Remove.
		await row.hover();
		await row.locator( '.bh-account-delete' ).click();
		const confirm = page.locator( '#bh-mailboxes-account-confirm' );
		await expect( confirm ).toBeVisible();
		await confirm
			.locator( '.bh-mailboxes-account-confirm__delete' )
			.click();

		await expect( row ).toHaveCount( 0 );

		await page.reload( { waitUntil: 'domcontentloaded' } );
		await expect( row ).toHaveCount( 0 );
	} );
} );
