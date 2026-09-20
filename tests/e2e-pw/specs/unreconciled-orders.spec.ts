/**
 * Playwright E2E test for the "Unreconciled orders" page, provided by bh-wp-order-email-reconcile and
 * registered as a hidden WooCommerce admin page by Unreconciled_Orders_Menu.
 *
 * An unpaid Venmo order is arranged via the WooCommerce REST API, the page is checked in the UI,
 * then the order is completed via REST and should no longer be listed.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../helpers/general/ui/login';
import { testConfig } from '../test-config';

const PAGE_URL = '/wp-admin/admin.php?page=bh-wp-oer-unreconciled-orders';

test.describe( 'Unreconciled orders page', () => {
	test( 'an unpaid Venmo order is listed on the hidden page and disappears once completed', async ( {
		page,
		requestUtils,
	} ) => {
		const products = await requestUtils.rest( {
			path: '/wc/v3/products',
			params: { search: testConfig.products.simple.name, per_page: 1 },
		} );
		const order = await requestUtils.rest( {
			method: 'POST',
			path: '/wc/v3/orders',
			data: {
				payment_method: 'venmo',
				status: 'on-hold',
				billing: { first_name: 'Una', last_name: 'Reconciled', email: 'unreconciled@example.com' },
				line_items: [ { product_id: products[ 0 ].id, quantity: 1 } ],
			},
		} );

		try {
			await loginAsAdmin( page );
			await page.goto( PAGE_URL, { waitUntil: 'domcontentloaded' } );

			await expect( page.getByRole( 'heading', { name: 'Unreconciled Orders' } ) ).toBeVisible();
			// Reachable by URL but not listed in the WooCommerce menu.
			await expect( page.locator( '#adminmenu' ).getByRole( 'link', { name: 'Unreconciled orders' } ) ).toHaveCount( 0 );

			const row = page.locator( `tr[data-order-id="${ order.id }"]` );
			await expect( row ).toBeVisible();
			await expect( row ).toContainText( 'Una Reconciled' );
			await expect( row ).toContainText( order.total );

			await requestUtils.rest( {
				method: 'PUT',
				path: `/wc/v3/orders/${ order.id }`,
				data: { status: 'completed' },
			} );

			await page.goto( PAGE_URL, { waitUntil: 'domcontentloaded' } );
			await expect( page.getByRole( 'heading', { name: 'Unreconciled Orders' } ) ).toBeVisible();
			await expect( page.locator( `tr[data-order-id="${ order.id }"]` ) ).toHaveCount( 0 );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wc/v3/orders/${ order.id }`,
				params: { force: true },
			} );
		}
	} );
} );
