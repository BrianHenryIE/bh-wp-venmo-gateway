/**
 * Playwright E2E tests for the Venmo Gateway shortcode checkout flow.
 *
 * Tests the WooCommerce shortcode checkout ([woocommerce_checkout]) with the
 * Venmo payment gateway — adding a product to cart, filling billing details,
 * entering a Venmo username, and placing an order.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/general/ui/login';
import { useShortcodeCheckout } from '../helpers/development-plugin/rest/checkout';

import { testConfig } from '../test-config';
import { setDefaultCustomerAddresses } from "../helpers/development-plugin/ajax/wc-cart";
import { addProductToCartByName } from "../helpers/general/ui/wc-cart";
import { setVenmoUsername } from '../helpers/venmo/rest/wc-payment-gateway';

const CUSTOMER_VENMO_USERNAME = 'brianhenryie';
const STORE_VENMO_USERNAME = 'sackavs';

test.describe( 'Venmo checkout (shortcode)', () => {

	test.beforeAll( async ( { browser, requestUtils } ) => {
		const page = await browser.newPage();

		// Ensure the checkout page uses the shortcode.
		await useShortcodeCheckout();

		// Delete all cookies so user is logged out and cart is empty
		await page.context().clearCookies();
	} );

	test.beforeEach( async ( { page } ) => {
		// Delete all cookies so user is logged out and cart is empty
		await page.context().clearCookies();

		// Set the store's Venmo username.
		await setVenmoUsername( STORE_VENMO_USERNAME );

		// Set the billing+shipping details via API.
		await setDefaultCustomerAddresses(page);

		// Add a product to cart.
		await addProductToCartByName( page, testConfig.products.simple.name );
	} );

	test( 'Venmo gateway is visible on checkout', async ( { page } ) => {

		// Go to checkout
		await page.goto( '/checkout/' );

		// The Venmo payment method should be present.
		const venmoLabel = page.locator( 'label[for="payment_method_venmo"]' );
		await expect( venmoLabel ).toBeVisible();
	} );

	test( 'can place an order with Venmo', async ( { page } ) => {

		// Go to checkout
		await page.goto( '/checkout/' );

		// Select Venmo gateway.
		await page.click( 'label[for="payment_method_venmo"]' );

		// Wait for the Venmo payment fields to appear.
		await expect(
			page.locator( '#_customer-venmo-username' )
		).toBeVisible();

		// Enter customer Venmo username.
		await page.fill( '#_customer-venmo-username', CUSTOMER_VENMO_USERNAME );

		// Place order.
		await page.click( '#place_order' );

		// Wait for order-received page.
		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Verify order received.
		await expect(
			page.locator( '.woocommerce-thankyou-order-received, .woocommerce-order-received' ).first()
		).toBeVisible();
	} );

	test( 'thank you page shows Venmo payment instructions', async ( {
		page,
	} ) => {

		// Go to checkout.
		await page.goto( '/checkout/' );

		// Select Venmo and fill username.
		await page.click( 'label[for="payment_method_venmo"]' );
		await page.fill( '#_customer-venmo-username', CUSTOMER_VENMO_USERNAME );

		// Place order.
		await page.click( '#place_order' );
		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Verify Venmo-specific instructions on thank you page.

		// Should show the order total.
		await expect(
			page.locator( 'text=Please send payment of' ).first()
		).toBeVisible();

		// The thank you page should mention the destination Venmo username.
		await expect(
			page.locator( 'text=via Venmo to @' + STORE_VENMO_USERNAME )
		).toBeVisible();

		// Should show a QR code linking to the Venmo profile.
		const qrImage = page.locator( 'img[alt="Payment QR code"]' );
		await expect( qrImage ).toBeVisible();

	} );

	test( 'Venmo username field is required', async ( { page } ) => {

		// Go to checkout.
		await page.goto( '/checkout/' );

		// Select Venmo but DON'T fill in the username.
		await page.click( 'label[for="payment_method_venmo"]' );

		// Place order — should stay on checkout (validation error).
		await page.click( '#place_order' );

		// Should NOT navigate to order-received.
		await page.waitForTimeout( 3000 );
		expect( page.url() ).not.toContain( 'order-received' );
	} );

	test( 'order shows on-hold status after Venmo payment', async ( {
		page,
	} ) => {

		// Go to checkout and complete order.
		await page.goto( '/checkout/' );

		await page.click( 'label[for="payment_method_venmo"]' );
		await page.fill( '#_customer-venmo-username', CUSTOMER_VENMO_USERNAME );
		await page.click( '#place_order' );
		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Extract order ID from URL.
		const url = page.url();
		const orderIdMatch = url.match( /order-received\/(\d+)/ );
		expect( orderIdMatch ).not.toBeNull();
		const orderId = orderIdMatch![ 1 ];

		// Log in as admin and check the order status.
		await loginAsAdmin( page );
		await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );

		// Order status should be "on-hold".
		const statusSelect = page.locator( '#order_status' );
		await expect( statusSelect ).toHaveValue( 'wc-on-hold' );

		// Order notes should mention the Venmo usernames.
		const orderNotes = page.locator( '#woocommerce-order-notes' );
		await expect( orderNotes ).toContainText( 'Awaiting Venmo payment' );
		await expect( orderNotes ).toContainText( 'from @' + CUSTOMER_VENMO_USERNAME );
		await expect( orderNotes ).toContainText( 'to @' + STORE_VENMO_USERNAME );
	} );
} );
