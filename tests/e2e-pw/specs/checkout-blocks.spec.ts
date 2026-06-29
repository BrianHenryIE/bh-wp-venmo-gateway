/**
 * Playwright E2E tests for the Venmo Gateway WooCommerce Blocks checkout flow.
 *
 * Tests the blocks-based checkout with the Venmo payment gateway — adding a product
 * to cart, selecting the Venmo payment method, entering a Venmo username, and placing
 * an order.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/general/ui/login';
import { BLOCKS_CHECKOUT_PATH } from "../helpers/development-plugin/rest/checkout";
import {setDefaultCustomerAddresses} from "../helpers/development-plugin/ajax/wc-cart";
import {addProductToCartByName} from "../helpers/general/ui/wc-cart";
import {testConfig} from "../test-config";
import { setVenmoUsername } from '../helpers/venmo/rest/wc-payment-gateway';

const CUSTOMER_VENMO_USERNAME = 'brianhenryie';
const STORE_VENMO_USERNAME = 'sackavs';

test.describe( 'Venmo checkout (blocks)', () => {

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

	test( 'Venmo gateway appears in blocks checkout', async ( { page } ) => {

		// Go to checkout.
		await page.goto( BLOCKS_CHECKOUT_PATH );

		// Wait for blocks checkout to load.
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// The Venmo payment method should be present.
		const venmoRadio = page.locator(
			'label[for="radio-control-wc-payment-method-options-venmo"]'
		);
		await expect( venmoRadio ).toBeVisible();
	} );

	test( 'Venmo username input appears when selected', async ( { page } ) => {

		// Go to checkout.
		await page.goto( BLOCKS_CHECKOUT_PATH );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// Select Venmo.
		await page.click(
			'label[for="radio-control-wc-payment-method-options-venmo"]'
		);

		// The username input should be visible.
		const usernameInput = page.locator( '#venmo-username-input' );
		await expect( usernameInput ).toBeVisible();

		// The label should be present.
		await expect(
			page.locator( 'text=Enter your Venmo username:' )
		).toBeVisible();
	} );

	test( 'can place an order with Venmo via blocks checkout', async ( {
		page,
	} ) => {

		// Go to checkout.
		await page.goto( BLOCKS_CHECKOUT_PATH );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// Select Venmo payment method.
		await page.click(
			'label[for="radio-control-wc-payment-method-options-venmo"]'
		);

		// Enter Venmo username.
		const usernameInput = page.locator( '#venmo-username-input' );
		await expect( usernameInput ).toBeVisible();
		await usernameInput.fill( CUSTOMER_VENMO_USERNAME );

		// Place order.
		await page.click(
			'.wc-block-components-checkout-place-order-button'
		);

		// Wait for order confirmation.
		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Verify order received.
		await expect(
			page.locator( 'text=Order received' ).first()
		).toBeVisible();
	} );

	test( 'blocks checkout validates empty Venmo username', async ( {
		page,
	} ) => {

		// Go to checkout.
		await page.goto( BLOCKS_CHECKOUT_PATH );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// Select Venmo but don't fill username.
		await page.click(
			'label[for="radio-control-wc-payment-method-options-venmo"]'
		);

		// Place order without entering username.
		await page.click(
			'.wc-block-components-checkout-place-order-button'
		);

		// Should show an error — not navigate to order-received.
		await page.waitForTimeout( 3000 );
		expect( page.url() ).not.toContain( 'order-received' );

		// Error notice should be visible.
		const errorNotice = page.locator(
			'.wc-block-components-notice-banner.is-error, .wc-block-store-notices .wc-block-components-notice-banner'
		);
		await expect( errorNotice.first() ).toBeVisible();
	} );

	test( 'thank you page shows Venmo instructions after blocks checkout', async ( {
		page,
	} ) => {

		// Go to checkout and fill everything.
		await page.goto( BLOCKS_CHECKOUT_PATH );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		// Select Venmo and enter username.
		await page.click(
			'label[for="radio-control-wc-payment-method-options-venmo"]'
		);
		await page.locator( '#venmo-username-input' ).fill( CUSTOMER_VENMO_USERNAME );

		// Place order.
		await page.click(
			'.wc-block-components-checkout-place-order-button'
		);

		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Verify Venmo instructions are shown — destination username.
		await expect( page.locator( 'text=@' + STORE_VENMO_USERNAME ).first() ).toBeVisible();

		// Verify QR code is present.
		const qrImage = page.locator( 'img[alt="Payment QR code"]' );
		await expect( qrImage ).toBeVisible();
	} );

	test( 'admin order shows Venmo meta from blocks checkout', async ( {
		page,
	} ) => {

		// Go to checkout and complete order.
		await page.goto( BLOCKS_CHECKOUT_PATH );
		await page.waitForSelector( '.wc-block-checkout', { timeout: 15000 } );

		await page.click(
			'label[for="radio-control-wc-payment-method-options-venmo"]'
		);
		await page.locator( '#venmo-username-input' ).fill( CUSTOMER_VENMO_USERNAME );

		await page.click(
			'.wc-block-components-checkout-place-order-button'
		);

		await page.waitForURL( /order-received/, { timeout: 30000 } );

		// Extract order ID from URL.
		const url = page.url();
		const orderIdMatch = url.match( /order-received\/(\d+)/ );
		expect( orderIdMatch ).not.toBeNull();
		const orderId = orderIdMatch![ 1 ];

		// Log in as admin and check the order.
		await loginAsAdmin( page );
		await page.goto(
			`/wp-admin/post.php?post=${ orderId }&action=edit`
		);

		// Order should be on-hold.
		const statusSelect = page.locator( '#order_status' );
		await expect( statusSelect ).toHaveValue( 'wc-on-hold' );

		// Order notes should reference both Venmo usernames.
		const orderNotes = page.locator( '#woocommerce-order-notes' );
		await expect( orderNotes ).toContainText( CUSTOMER_VENMO_USERNAME );
		await expect( orderNotes ).toContainText( STORE_VENMO_USERNAME );
	} );
} );
