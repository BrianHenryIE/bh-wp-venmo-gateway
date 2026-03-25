/**
 * Playwright E2E tests for the Venmo username todo features:
 *
 * 1. Save Venmo username to customer usermeta (logged-in)
 * 2. Save Venmo username to cookie (guest)
 * 3. Auto-fill username at checkout from usermeta/cookie/previous orders
 * 4. Focus username field when Venmo is selected
 * 5. Focus username field on validation error
 * 6. Show from/to usernames on order-received page
 * 7. Show from/to usernames in order emails
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import * as fs from 'fs';
import * as path from 'path';
import { login, loginAsAdmin, logout } from '../helpers/general/ui/login';
import { useShortcodeCheckout, useBlocksCheckout } from '../helpers/development-plugin/rest/checkout';
import { setDefaultCustomerAddresses } from '../helpers/development-plugin/ajax/wc-cart';
import { addProductToCartByName } from '../helpers/general/ui/wc-cart';
import { setVenmoUsername } from '../helpers/venmo/rest/wc-payment-gateway';
import { testConfig } from '../test-config';

/**
 * Set the checkout page to blocks checkout via authenticated REST API.
 */
async function setBlocksCheckoutViaRest( requestUtils: any ) {
	const blocksContentPath = path.join( __dirname, '../../_wp-env/blocks-checkout-post-content.txt' );
	let blocksContent: string;
	try {
		blocksContent = fs.readFileSync( blocksContentPath, 'utf8' );
	} catch {
		// Fallback: minimal blocks checkout content
		blocksContent = '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout wc-block-checkout is-loading"></div><!-- /wp:woocommerce/checkout -->';
	}

	const pages = await requestUtils.rest( {
		method: 'GET',
		path: '/wp/v2/pages',
		data: { search: 'Checkout', per_page: 100 },
	} );
	const checkoutPage = pages.find( ( p: any ) => p.title.rendered.toLowerCase() === 'checkout' );
	if ( checkoutPage ) {
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/pages/${ checkoutPage.id }`,
			data: { content: blocksContent },
		} );
	}
}

/**
 * Restore the checkout page to shortcode checkout via authenticated REST API.
 */
async function setShortcodeCheckoutViaRest( requestUtils: any ) {
	const pages = await requestUtils.rest( {
		method: 'GET',
		path: '/wp/v2/pages',
		data: { search: 'Checkout', per_page: 100 },
	} );
	const checkoutPage = pages.find( ( p: any ) => p.title.rendered.toLowerCase() === 'checkout' );
	if ( checkoutPage ) {
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/pages/${ checkoutPage.id }`,
			data: { content: '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->' },
		} );
	}
}

const CUSTOMER_VENMO_USERNAME = 'testcustomer_venmo';
const STORE_VENMO_USERNAME = 'sackavs';

/**
 * Place a Venmo order via the shortcode checkout as the current user/guest.
 * Assumes product is already in cart and billing address is set.
 */
async function placeVenmoOrder( page, customerUsername: string ) {
	await page.goto( '/checkout/' );
	await page.waitForLoadState( 'networkidle' );
	await page.click( 'label[for="payment_method_venmo"]' );
	await expect( page.locator( '#_customer-venmo-username' ) ).toBeVisible();
	await page.fill( '#_customer-venmo-username', customerUsername );
	await page.click( '#place_order' );
	await page.waitForURL( /order-received/, { timeout: 30000 } );
}

/**
 * Create a WP customer user via REST API.
 */
async function createCustomer( requestUtils, suffix: string ) {
	const username = `venmo_test_${ suffix }`;
	const email = `venmo_test_${ suffix }@example.com`;
	const password = 'password';

	const user = await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/users',
		data: {
			username,
			email,
			password,
			first_name: 'Venmo',
			last_name: 'Tester',
			roles: [ 'customer' ],
		},
	} );

	return { id: user.id, username, email, password };
}

/**
 * Delete a WP user.
 */
async function deleteCustomer( requestUtils, userId: number ) {
	await requestUtils.rest( {
		method: 'DELETE',
		path: `/wp/v2/users/${ userId }?force=true&reassign=1`,
	} );
}

// ──────────────────────────────────────────────────────────────────
// SHORTCODE CHECKOUT TESTS
// ──────────────────────────────────────────────────────────────────

test.describe( 'Venmo username features (shortcode)', () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();
		await useShortcodeCheckout();
		await page.context().clearCookies();
		await page.close();
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await page.goto( 'about:blank' );
		await setVenmoUsername( STORE_VENMO_USERNAME );
	} );

	// ─── TODO 1: Save username to customer usermeta ────────

	test( 'saves Venmo username to customer usermeta after order (verified via auto-fill)', async ( {
		page,
		requestUtils,
	} ) => {
		const customer = await createCustomer( requestUtils, Date.now().toString() );

		try {
			// Log in as the customer.
			await login( { username: customer.username, password: customer.password }, page );

			// Set addresses and add product.
			await setDefaultCustomerAddresses( page );
			await addProductToCartByName( page, testConfig.products.simple.name );

			// Place order with Venmo — this should save username to usermeta.
			await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

			// Verify usermeta was saved by starting a new checkout and checking auto-fill.
			await addProductToCartByName( page, testConfig.products.simple.name );
			await page.goto( '/checkout/' );
			await page.waitForLoadState( 'networkidle' );
			await page.click( 'label[for="payment_method_venmo"]' );

			const usernameField = page.locator( '#_customer-venmo-username' );
			await expect( usernameField ).toBeVisible();
			await expect( usernameField ).toHaveValue( CUSTOMER_VENMO_USERNAME );
		} finally {
			await deleteCustomer( requestUtils, customer.id );
		}
	} );

	// ─── TODO 2: Save username to cookie for guests ────────

	test( 'saves Venmo username to cookie for guest checkout', async ( {
		page,
	} ) => {
		// Ensure guest (no cookies).
		await page.context().clearCookies();

		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

		// Check for the venmo_username cookie.
		const cookies = await page.context().cookies();
		const venmoCookie = cookies.find( ( c ) => c.name === 'venmo_username' );
		expect( venmoCookie ).toBeDefined();
		expect( venmoCookie!.value ).toBe( CUSTOMER_VENMO_USERNAME );
	} );

	// ─── TODO 3: Auto-fill from usermeta ────────

	test( 'auto-fills Venmo username from usermeta for logged-in customer', async ( {
		page,
		requestUtils,
	} ) => {
		const customer = await createCustomer( requestUtils, Date.now().toString() );

		try {
			// Log in and place first order to save username.
			await login( { username: customer.username, password: customer.password }, page );
			await setDefaultCustomerAddresses( page );
			await addProductToCartByName( page, testConfig.products.simple.name );
			await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

			// Now start a second checkout — username should be pre-filled.
			await addProductToCartByName( page, testConfig.products.simple.name );
			await page.goto( '/checkout/' );
			await page.waitForLoadState( 'networkidle' );
			await page.click( 'label[for="payment_method_venmo"]' );

			const usernameField = page.locator( '#_customer-venmo-username' );
			await expect( usernameField ).toBeVisible();
			await expect( usernameField ).toHaveValue( CUSTOMER_VENMO_USERNAME );
		} finally {
			await deleteCustomer( requestUtils, customer.id );
		}
	} );

	// ─── TODO 3: Auto-fill from cookie (guest) ────────

	test( 'auto-fills Venmo username from cookie for returning guest', async ( {
		page,
	} ) => {
		// Guest checkout — first order sets the cookie.
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );
		await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

		// Verify the venmo_username cookie was set.
		const cookies = await page.context().cookies();
		const venmoCookie = cookies.find( ( c ) => c.name === 'venmo_username' );
		expect( venmoCookie ).toBeDefined();
		expect( venmoCookie!.value ).toBe( CUSTOMER_VENMO_USERNAME );

		// Now add another product and go to checkout — username should be pre-filled from cookie.
		await addProductToCartByName( page, testConfig.products.simple.name );
		await page.goto( '/checkout/' );
		await page.waitForLoadState( 'networkidle' );
		await page.click( 'label[for="payment_method_venmo"]' );

		const usernameField = page.locator( '#_customer-venmo-username' );
		await expect( usernameField ).toBeVisible();
		await expect( usernameField ).toHaveValue( CUSTOMER_VENMO_USERNAME );
	} );

	// ─── TODO 4: Focus when Venmo selected ────────

	test.fixme( 'focuses username field when Venmo payment method is selected', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await page.goto( '/checkout/' );
		await page.waitForLoadState( 'networkidle' );

		// Select a different payment method first (e.g., cheque or cod).
		const otherMethod = page.locator( 'input[name="payment_method"]' ).first();
		if ( await otherMethod.isVisible() && await otherMethod.getAttribute( 'value' ) !== 'venmo' ) {
			await otherMethod.check();
			await page.waitForTimeout( 500 );
		}

		// Now select Venmo.
		await page.click( 'label[for="payment_method_venmo"]' );
		await page.waitForTimeout( 300 ); // Wait for JS focus handler.

		// Verify the username field has focus.
		const focusedId = await page.evaluate( () => document.activeElement?.id );
		expect( focusedId ).toBe( '_customer-venmo-username' );
	} );

	// ─── TODO 5: Focus on validation error ────────

	test.fixme( 'focuses username field when Place Order clicked without username', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await page.goto( '/checkout/' );
		await page.waitForLoadState( 'networkidle' );

		// Select Venmo but leave username empty.
		await page.click( 'label[for="payment_method_venmo"]' );
		await page.fill( '#_customer-venmo-username', '' );

		// Click Place Order.
		await page.click( '#place_order' );

		// Wait for checkout error response and JS to handle it.
		await page.waitForTimeout( 3000 );

		// Should not navigate away.
		expect( page.url() ).not.toContain( 'order-received' );

		// An error notice should be visible on the page.
		const hasError = await page.locator( '.woocommerce-error, .woocommerce-NoticeGroup-checkout' ).first().isVisible().catch( () => false );
		expect( hasError ).toBeTruthy();

		// Username field should be focused (if the focus JS is working).
		const focusedId = await page.evaluate( () => document.activeElement?.id );
		expect( focusedId ).toBe( '_customer-venmo-username' );
	} );

	// ─── TODO 6: From/to on thank you page ────────

	test( 'thank you page shows from/to Venmo usernames', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

		// Verify the from/to line is displayed.
		await expect(
			page.locator( `text=Payment from @${ CUSTOMER_VENMO_USERNAME } to @${ STORE_VENMO_USERNAME }` )
		).toBeVisible();
	} );

	// ─── TODO 7: From/to in emails ────────

	test( 'order on-hold note includes from/to Venmo usernames', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

		// Extract order ID from URL.
		const url = page.url();
		const orderIdMatch = url.match( /order-received\/(\d+)/ );
		expect( orderIdMatch ).not.toBeNull();
		const orderId = orderIdMatch![ 1 ];

		// Log in as admin and check the order.
		await loginAsAdmin( page );
		await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );

		// Order notes should contain both usernames.
		const orderNotes = page.locator( '#woocommerce-order-notes' );
		await expect( orderNotes ).toContainText( `@${ CUSTOMER_VENMO_USERNAME }` );
		await expect( orderNotes ).toContainText( `@${ STORE_VENMO_USERNAME }` );
	} );

	// ─── TODO 7: Email content verification (via WP mailhog or order meta) ────

	test( 'order meta stores both customer and store Venmo usernames (for emails)', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

		// Extract order ID from URL.
		const url = page.url();
		const orderIdMatch = url.match( /order-received\/(\d+)/ );
		expect( orderIdMatch ).not.toBeNull();
		const orderId = orderIdMatch![ 1 ];

		// Log in as admin and check order meta via admin UI.
		await loginAsAdmin( page );
		await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );

		// Check that the order notes mention both usernames (set during process_payment).
		const orderNotes = page.locator( '#woocommerce-order-notes' );
		await expect( orderNotes ).toContainText( `@${ CUSTOMER_VENMO_USERNAME }` );
		await expect( orderNotes ).toContainText( `@${ STORE_VENMO_USERNAME }` );
	} );
} );

// ──────────────────────────────────────────────────────────────────
// BLOCKS CHECKOUT TESTS
// ──────────────────────────────────────────────────────────────────

test.describe( 'Venmo username features (blocks)', () => {

	test.beforeAll( async ( { requestUtils } ) => {
		await setBlocksCheckoutViaRest( requestUtils );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await setShortcodeCheckoutViaRest( requestUtils );
	} );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
		await setVenmoUsername( STORE_VENMO_USERNAME );
	} );

	// ─── TODO 3: Auto-fill in blocks checkout ────────

	test( 'blocks: auto-fills Venmo username from usermeta (saved via shortcode order)', async ( {
		page,
		requestUtils,
	} ) => {
		const customer = await createCustomer( requestUtils, Date.now().toString() );

		try {
			// Log in as customer.
			await login( { username: customer.username, password: customer.password }, page );

			// First, place an order via shortcode checkout to save the username to usermeta.
			await setShortcodeCheckoutViaRest( requestUtils );
			await setDefaultCustomerAddresses( page );
			await addProductToCartByName( page, testConfig.products.simple.name );
			await placeVenmoOrder( page, CUSTOMER_VENMO_USERNAME );

			// Switch back to blocks checkout.
			await setBlocksCheckoutViaRest( requestUtils );

			// Add product and go to blocks checkout.
			await addProductToCartByName( page, testConfig.products.simple.name );
			await page.goto( '/checkout/' );
			await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

			// Select Venmo.
			await page.click( 'label[for="radio-control-wc-payment-method-options-venmo"]' );

			// Username should be pre-filled from usermeta.
			const usernameInput = page.locator( '#venmo-username-input' );
			await expect( usernameInput ).toBeVisible();
			await expect( usernameInput ).toHaveValue( CUSTOMER_VENMO_USERNAME );
		} finally {
			// Restore blocks checkout for remaining tests.
			await setBlocksCheckoutViaRest( requestUtils );
			await deleteCustomer( requestUtils, customer.id );
		}
	} );

	// ─── TODO 4: Focus in blocks checkout ────────

	test( 'blocks: focuses username field when Venmo is selected', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await page.goto( '/checkout/' );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// Select Venmo.
		await page.click( 'label[for="radio-control-wc-payment-method-options-venmo"]' );
		await page.waitForTimeout( 300 );

		// Verify the username field has focus.
		const focusedId = await page.evaluate( () => document.activeElement?.id );
		expect( focusedId ).toBe( 'venmo-username-input' );
	} );

	// ─── TODO 5: Focus on validation error (blocks) ────────

	test( 'blocks: focuses username field on validation error', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await page.goto( '/checkout/' );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// Select Venmo but leave username empty.
		await page.click( 'label[for="radio-control-wc-payment-method-options-venmo"]' );
		const usernameInput = page.locator( '#venmo-username-input' );
		await usernameInput.clear();

		// Click place order.
		await page.click( '.wc-block-components-checkout-place-order-button' );
		await page.waitForTimeout( 1000 );

		// Should not navigate away.
		expect( page.url() ).not.toContain( 'order-received' );

		// Error should be visible.
		const errorNotice = page.locator(
			'.wc-block-components-notice-banner.is-error, .wc-block-store-notices .wc-block-components-notice-banner'
		);
		await expect( errorNotice.first() ).toBeVisible();

		// Username field should be focused.
		const focusedId = await page.evaluate( () => document.activeElement?.id );
		expect( focusedId ).toBe( 'venmo-username-input' );
	} );

	// ─── TODO 6: From/to on thank you page (blocks) ────────
	// Known issue: blocks checkout saves meta via woocommerce_store_api_checkout_order_processed
	// which fires AFTER process_payment(), so customer username isn't available on thank-you page.

	test.fixme( 'blocks: thank you page shows from/to Venmo usernames', async ( {
		page,
	} ) => {
		await page.context().clearCookies();
		await setDefaultCustomerAddresses( page );
		await addProductToCartByName( page, testConfig.products.simple.name );

		await page.goto( '/checkout/' );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		await page.click( 'label[for="radio-control-wc-payment-method-options-venmo"]' );
		await page.locator( '#venmo-username-input' ).fill( CUSTOMER_VENMO_USERNAME );
		await page.click( '.wc-block-components-checkout-place-order-button' );
		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Verify from/to line on thank you page.
		await expect(
			page.locator( `text=Payment from @${ CUSTOMER_VENMO_USERNAME } to @${ STORE_VENMO_USERNAME }` )
		).toBeVisible();
	} );

	// ─── TODO 1: Save username to usermeta (blocks) ────────

	test( 'blocks: saves Venmo username to customer usermeta (verified via second checkout)', async ( {
		page,
		requestUtils,
	} ) => {
		const customer = await createCustomer( requestUtils, Date.now().toString() );

		try {
			await login( { username: customer.username, password: customer.password }, page );
			await setDefaultCustomerAddresses( page );
			await addProductToCartByName( page, testConfig.products.simple.name );

			await page.goto( '/checkout/' );
			await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

			await page.click( 'label[for="radio-control-wc-payment-method-options-venmo"]' );
			await page.locator( '#venmo-username-input' ).fill( CUSTOMER_VENMO_USERNAME );
			await page.click( '.wc-block-components-checkout-place-order-button' );
			await page.waitForURL( /order-received/, { timeout: 30000 } );

			// Verify usermeta was saved by starting a second checkout.
			// The blocks checkout reads saved_venmo_username from settings data.
			await addProductToCartByName( page, testConfig.products.simple.name );
			await page.goto( '/checkout/' );
			await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

			await page.click( 'label[for="radio-control-wc-payment-method-options-venmo"]' );
			const usernameInput = page.locator( '#venmo-username-input' );
			await expect( usernameInput ).toBeVisible();
			await expect( usernameInput ).toHaveValue( CUSTOMER_VENMO_USERNAME );
		} finally {
			await deleteCustomer( requestUtils, customer.id );
		}
	} );
} );
