/**
 * Playwright E2E test for the development plugin's `?add-to-cart-sku=` link (Add_To_Cart_By_Sku),
 * used as the WordPress Playground preview's landing page.
 *
 * The WooCommerce sample products are imported by tests/_wp-env/initialize-internal.sh. The cart
 * is asserted via the Store API, using the page's session cookie.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Development plugin – add to cart by SKU', () => {
	// As a guest, so each browser has its own cart: a logged-in user's cart persists across sessions.
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'adds the product to the cart and opens the checkout', async ( { page } ) => {
		await page.goto( '/checkout/?add-to-cart-sku=woo-beanie' );

		// Redirected to the checkout without the query arg, so a reload does not add it again.
		await expect( page ).toHaveURL( /\/checkout\/$/ );

		await page.reload();

		const cart = await ( await page.request.get( '/wp-json/wc/store/v1/cart' ) ).json();
		const items = cart.items.map( ( item: { name: string; quantity: number } ) => ( {
			name: item.name,
			quantity: item.quantity,
		} ) );
		expect( items ).toEqual( [ { name: 'Beanie', quantity: 1 } ] );
	} );
} );
