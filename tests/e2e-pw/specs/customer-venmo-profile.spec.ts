/**
 * When a Venmo order is placed, a background task fetches the customer's full name from their public Venmo
 * profile (venmo.com/u/{username}) and records it in the order meta.
 *
 * Arranged over REST (the profile page is mocked by the development plugin; the on-hold Venmo order is created
 * with the WooCommerce REST API), the WP-Cron event is run with WP-CLI, and the result is asserted over REST.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { testConfig } from '../test-config';
import { mockVenmoProfile, removeMockVenmoProfile } from '../helpers/development-plugin/rest/venmo-profiles';
import { runDueCronHook } from '../helpers/general/cli/wp-cli';

const CUSTOMER_VENMO_USERNAME = 'e2e-profile-tester';
const CUSTOMER_VENMO_DISPLAY_NAME = 'Zed Profile Tester';
const CRON_HOOK = 'bh_wp_venmo_gateway_fetch_customer_venmo_profile';

test.describe( 'Customer Venmo profile name', () => {
	// Running the cron event through `wp-env run cli` takes several seconds, more so when browsers run in parallel.
	test.setTimeout( 90_000 );

	test.beforeAll( async () => {
		await mockVenmoProfile( CUSTOMER_VENMO_USERNAME, CUSTOMER_VENMO_DISPLAY_NAME );
	} );

	test.afterAll( async () => {
		await removeMockVenmoProfile( CUSTOMER_VENMO_USERNAME );
	} );

	test( 'is fetched in the background and saved to the order meta', async ( { requestUtils } ) => {
		const products = await requestUtils.rest( {
			path: '/wc/v3/products',
			params: { search: testConfig.products.simple.name, per_page: 1 },
		} );
		expect( products.length ).toBe( 1 );

		// Arrange (REST): an on-hold Venmo order, as process_payment() leaves it, with the username entered at checkout.
		const order = await requestUtils.rest( {
			method: 'POST',
			path: '/wc/v3/orders',
			data: {
				payment_method: 'venmo',
				status: 'on-hold',
				billing: { first_name: 'Zed', last_name: 'Tester' },
				line_items: [ { product_id: products[ 0 ].id, quantity: 1 } ],
				meta_data: [ { key: '_customer-venmo-username', value: CUSTOMER_VENMO_USERNAME } ],
			},
		} );

		try {
			// Act: run the scheduled background task.
			runDueCronHook( CRON_HOOK );

			// Assert (REST): the display name is on the order, once, and an order note records it.
			const updatedOrder = await requestUtils.rest( { path: `/wc/v3/orders/${ order.id }` } );
			const displayNames = updatedOrder.meta_data.filter( ( meta: { key: string } ) => '_customer-venmo-display-name' === meta.key );
			expect( displayNames ).toHaveLength( 1 );
			expect( displayNames[ 0 ].value ).toBe( CUSTOMER_VENMO_DISPLAY_NAME );

			const notes = await requestUtils.rest( { path: `/wc/v3/orders/${ order.id }/notes` } );
			expect( notes.some( ( n: { note: string } ) => n.note === `Venmo profile @${ CUSTOMER_VENMO_USERNAME } is ${ CUSTOMER_VENMO_DISPLAY_NAME }.` ) ).toBe( true );
		} finally {
			await requestUtils.rest( { method: 'DELETE', path: `/wc/v3/orders/${ order.id }`, params: { force: true } } );
		}
	} );
} );
