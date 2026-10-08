/**
 * Playwright E2E test: a WooCommerce shop manager can use the WooCommerce admin while this plugin is active.
 *
 * Regression test: with the plugin's development environment active, the WooCommerce admin (wc-admin home,
 * orders) was unusable for shop managers because its REST requests were refused with 403 (visible as a flood
 * of errors in the browser's network and console tabs).
 *
 * Arrange: nothing (the `shopmanager` user is created in tests/_wp-env/initialize-internal.sh).
 * Act: log in as the shop manager through the UI and open the WooCommerce admin pages.
 * Assert: no request from those pages is refused with 403, and the page's main content renders.
 */
import { test, expect, Page } from '@wordpress/e2e-test-utils-playwright';
import { login, logout } from '../helpers/general/ui/login';
import { testConfig } from '../test-config';

const WC_ADMIN_HOME = '/wp-admin/admin.php?page=wc-admin';
const WC_ORDERS = '/wp-admin/admin.php?page=wc-orders';

/**
 * Collect every response on the page whose status is 403, as "STATUS METHOD URL" for a readable assertion.
 *
 * @param page The page to listen on.
 */
function collectForbiddenResponses( page: Page ): string[] {
	const forbidden: string[] = [];
	page.on( 'response', ( response ) => {
		if ( 403 === response.status() ) {
			forbidden.push(
				`${ response.status() } ${ response
					.request()
					.method() } ${ response.url() }`
			);
		}
	} );
	return forbidden;
}

test.describe( 'WooCommerce shop manager in the WooCommerce admin', () => {
	test.beforeEach( async ( { page } ) => {
		await logout( page );
		await login( testConfig.users.shopManager, page );
	} );

	test( 'the WooCommerce home page loads without 403 responses', async ( {
		page,
	} ) => {
		const forbidden = collectForbiddenResponses( page );

		await page.goto( WC_ADMIN_HOME, { waitUntil: 'domcontentloaded' } );

		// The React app has booted and issued its REST requests once the layout renders.
		await expect(
			page.locator( '.woocommerce-layout__primary' )
		).toBeVisible();
		// Let those requests settle; the page may keep polling, so a timeout here is not a failure.
		await page
			.waitForLoadState( 'networkidle', { timeout: 10_000 } )
			.catch( () => undefined );

		expect( forbidden, forbidden.join( '\n' ) ).toEqual( [] );
	} );

	test( 'the orders page loads without 403 responses', async ( { page } ) => {
		const forbidden = collectForbiddenResponses( page );

		await page.goto( WC_ORDERS, { waitUntil: 'domcontentloaded' } );

		await expect( page.locator( '.wp-list-table' ) ).toBeVisible();
		await page
			.waitForLoadState( 'networkidle', { timeout: 10_000 } )
			.catch( () => undefined );

		expect( forbidden, forbidden.join( '\n' ) ).toEqual( [] );
	} );
} );
