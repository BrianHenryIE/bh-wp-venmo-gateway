/**
 * Playwright E2E tests for non-administrator access to the payment emails.
 *
 * The bh-wp-mailboxes library requires `manage_options` for everything by default; the plugin's
 * `Capabilities` class lowers that for the emails and the email accounts to `manage_woocommerce`
 * (WooCommerce shop managers) and `manage_give_settings` (GiveWP managers). The logs page stays
 * administrator-only.
 *
 * Arrange: an email is delivered to the REST ingress endpoint as the administrator (the default storage
 * state). Act/assert: each role logs in through the UI and visits the admin screens. Assertions are via
 * the UI because the development plugin authenticates every unauthenticated REST request as the administrator.
 *
 * The `shopmanager`, `givemanager` and `contributor` users are created in tests/_wp-env/initialize-internal.sh.
 */
import * as fs from 'fs';
import * as path from 'path';
import { test, expect, Page } from '@wordpress/e2e-test-utils-playwright';
import { login, logout } from '../helpers/general/ui/login';
import { testConfig } from '../test-config';

const EMAILS_LIST = '/wp-admin/edit.php?post_type=venmo_payment_emails';
const ACCOUNTS_LIST = '/wp-admin/edit.php?post_type=venmo_email_accounts';
const UNRECONCILED_ORDERS = '/wp-admin/admin.php?page=bh-wp-oer-unreconciled-orders';
const LOGS_PAGE = '/wp-admin/admin.php?page=bh-wp-venmo-gateway-logs';
const INGRESS = '/wp-json/bh-wp-venmo-gateway/v2/venmo-payment-emails/new';

/**
 * Deliver the fixture email with a unique Message-ID as the administrator; returns the email post id.
 *
 * A REST nonce is needed alongside the admin cookies: without one, WordPress treats the request as
 * unauthenticated (`rest_cookie_check_errors()`), regardless of the development plugin's REST auth shortcut.
 */
async function createEmail( page: Page ): Promise< number > {
	const nonce = await ( await page.request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' ) ).text();
	const eml = fs
		.readFileSync( path.resolve( __dirname, '../../_data/John Doe paid you $46.00.eml' ), 'utf8' )
		.replace( /^Message-ID: .*$/m, `Message-ID: <roles-spec-${ Date.now() }-${ Math.random().toString( 36 ).slice( 2 ) }@example.org>` );
	const response = await page.request.post( INGRESS, {
		headers: { 'Content-Type': 'message/rfc822', 'X-WP-Nonce': nonce },
		data: eml,
	} );
	expect( response.status(), await response.text() ).toBe( 201 );
	return ( await response.json() ).post_id;
}

async function expectNotAllowed( page: Page, url: string ): Promise< void > {
	await page.goto( url, { waitUntil: 'domcontentloaded' } );
	await expect( page.locator( 'body' ) ).toContainText( /not allowed|do not have sufficient permissions/i );
}

for ( const role of [
	{ name: 'WooCommerce shop manager', user: testConfig.users.shopManager },
	{ name: 'GiveWP manager', user: testConfig.users.giveManager },
] ) {
	test.describe( `${ role.name } access`, () => {
		test( 'can view and process emails and manage accounts but not view logs', async ( { page } ) => {
			const emailId = await createEmail( page );

			await logout( page );
			await login( role.user, page );

			// Emails list: reachable, with the account-management controls.
			await page.goto( EMAILS_LIST, { waitUntil: 'domcontentloaded' } );
			await expect( page.locator( '.wp-list-table' ).first() ).toBeVisible();
			await expect( page.locator( `#post-${ emailId }` ) ).toBeVisible();
			await expect( page.locator( '#check-email' ) ).toHaveCount( 1 );
			await expect( page.locator( '.bh-account-add' ) ).toHaveCount( 1 );

			// Single email: the status metabox (the actions) is rendered.
			await page.goto( `/wp-admin/post.php?post=${ emailId }&action=edit`, { waitUntil: 'domcontentloaded' } );
			await expect( page.locator( '#title' ) ).toHaveValue( 'John Doe paid you $46.00' );
			await expect( page.locator( '#bh-email-local-status' ) ).toBeVisible();

			// Orders and donations awaiting a payment email.
			await page.goto( UNRECONCILED_ORDERS, { waitUntil: 'domcontentloaded' } );
			await expect( page.getByRole( 'heading', { name: 'Unreconciled Orders' } ) ).toBeVisible();

			// The email accounts list screen.
			await page.goto( ACCOUNTS_LIST, { waitUntil: 'domcontentloaded' } );
			await expect( page.locator( '.wp-list-table' ).first() ).toBeVisible();

			// Administrator-only: the logs.
			await expectNotAllowed( page, LOGS_PAGE );
		} );
	} );
}

test.describe( 'contributor access', () => {
	test( 'cannot view the emails, the accounts, or the unreconciled orders', async ( { page } ) => {
		const emailId = await createEmail( page );

		await logout( page );
		await login( testConfig.users.contributor, page );

		await expectNotAllowed( page, EMAILS_LIST );
		await expectNotAllowed( page, `/wp-admin/post.php?post=${ emailId }&action=edit` );
		await expectNotAllowed( page, ACCOUNTS_LIST );
		await expectNotAllowed( page, UNRECONCILED_ORDERS );
		await expectNotAllowed( page, LOGS_PAGE );
	} );
} );

test.describe( 'administrator access', () => {
	test( 'sees the account-management controls on the emails list', async ( { page } ) => {
		await createEmail( page );
		await page.goto( EMAILS_LIST, { waitUntil: 'domcontentloaded' } );
		await expect( page.locator( '#check-email' ) ).toHaveCount( 1 );
	} );
} );
