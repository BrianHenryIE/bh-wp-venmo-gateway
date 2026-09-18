/**
 * Playwright E2E test for the "View orders awaiting Venmo payment" link on the WooCommerce
 * gateway settings page (Orders_List_Filter).
 *
 * Orders are arranged via the WooCommerce REST API: one on-hold and one pending Venmo order that
 * should be listed, plus a completed Venmo order and an on-hold non-Venmo order that should not.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/general/ui/login';
import { testConfig } from '../test-config';

const SETTINGS_URL = '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=venmo';

test.describe( 'Orders awaiting Venmo payment link', () => {
	test( 'settings page links to the orders list filtered to pending and on-hold Venmo orders', async ( {
		page,
		requestUtils,
	} ) => {
		const products = await requestUtils.rest( {
			path: '/wc/v3/products',
			params: { search: testConfig.products.simple.name, per_page: 1 },
		} );
		const createOrder = ( payment_method: string, status: string ) =>
			requestUtils.rest( {
				method: 'POST',
				path: '/wc/v3/orders',
				data: {
					payment_method,
					status,
					billing: { first_name: 'Awaiting', last_name: status },
					line_items: [ { product_id: products[ 0 ].id, quantity: 1 } ],
				},
			} );

		const onHoldVenmo = await createOrder( 'venmo', 'on-hold' );
		const pendingVenmo = await createOrder( 'venmo', 'pending' );
		const completedVenmo = await createOrder( 'venmo', 'completed' );
		const onHoldOther = await createOrder( 'bacs', 'on-hold' );
		const orders = [ onHoldVenmo, pendingVenmo, completedVenmo, onHoldOther ];

		try {
			await loginAsAdmin( page );
			await page.goto( SETTINGS_URL, { waitUntil: 'domcontentloaded' } );

			const link = page.getByRole( 'link', { name: 'View orders awaiting Venmo payment' } );
			await expect( link ).toBeVisible();
			await link.click();
			await page.waitForURL( /bh_wp_venmo_gateway_awaiting_payment=1/ );

			// The orders list (HPOS `page=wc-orders` or legacy `post_type=shop_order`).
			await expect( page ).toHaveURL( /(page=wc-orders|post_type=shop_order)/ );
			const table = page.locator( 'table.wp-list-table' );
			await expect( table ).toBeVisible();

			// HPOS rows are `#order-{id}`; legacy posts-list rows are `#post-{id}`.
			const row = ( id: number ) => page.locator( `#order-${ id }, #post-${ id }` );
			await expect( row( onHoldVenmo.id ) ).toBeVisible();
			await expect( row( pendingVenmo.id ) ).toBeVisible();
			await expect( row( completedVenmo.id ) ).toHaveCount( 0 );
			await expect( row( onHoldOther.id ) ).toHaveCount( 0 );
		} finally {
			for ( const order of orders ) {
				await requestUtils.rest( { method: 'DELETE', path: `/wc/v3/orders/${ order.id }`, params: { force: true } } );
			}
		}
	} );
} );
