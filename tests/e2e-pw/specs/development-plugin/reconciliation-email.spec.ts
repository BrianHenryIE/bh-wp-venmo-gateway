/**
 * Playwright E2E test for the development plugin's "Send reconciliation email" button
 * on the admin order screen (Reconciliation_Email_Metabox).
 *
 * An unpaid Venmo order is arranged via the WooCommerce REST API, the button is clicked
 * in the UI, and the result is asserted via REST: the order gets a note linking to the
 * created email post.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../../helpers/general/ui/login';
import { testConfig } from '../../test-config';

test.describe( 'Development plugin – send reconciliation email', () => {
	test( 'button on the order screen creates a Venmo email in the mailbox', async ( {
		page,
		requestUtils,
	} ) => {
		// The button runs the email reconciliation over every unpaid order and donation on the site,
		// which on a long-lived dev site can take longer than the default timeout when browsers run in parallel.
		test.slow();

		// Arrange (REST): an on-hold Venmo order for the test product.
		const products = await requestUtils.rest( {
			path: '/wc/v3/products',
			params: { search: testConfig.products.simple.name, per_page: 1 },
		} );
		expect( products.length ).toBe( 1 );

		const order = await requestUtils.rest( {
			method: 'POST',
			path: '/wc/v3/orders',
			data: {
				payment_method: 'venmo',
				status: 'on-hold',
				billing: { first_name: 'Zed', last_name: 'Tester' },
				line_items: [ { product_id: products[ 0 ].id, quantity: 1 } ],
			},
		} );

		try {
			// Act (UI): click the button in the metabox.
			await loginAsAdmin( page );
			await page.goto( `/wp-admin/post.php?post=${ order.id }&action=edit`, { waitUntil: 'domcontentloaded' } );

			const button = page.locator( '#bh-wp-venmo-gateway-send-reconciliation-email' );
			await expect( button ).toBeVisible();
			await button.click();
			await page.waitForURL( /bh_wp_venmo_gateway_reconciliation_email=\d+/ );

			await expect( page.locator( '#bh-wp-venmo-gateway-reconciliation-email-notice' ) ).toContainText(
				'Created Venmo reconciliation email post #'
			);

			// Assert (REST): the order note names the created email, with the customer's name and total in the subject.
			const notes = await requestUtils.rest( { path: `/wc/v3/orders/${ order.id }/notes` } );
			const devNote = notes.find( ( n: { note: string } ) => n.note.includes( 'Development plugin: created Venmo reconciliation email' ) );
			expect( devNote ).toBeDefined();
			expect( devNote.note ).toContain( `"Zed Tester paid you $${ order.total }"` );

			// The note links to the email's edit screen (the single email view).
			const emailPostId = devNote.note.match( /post #(\d+)/ )[ 1 ];
			// esc_url() encodes the ampersand as `&#038;`.
			expect( devNote.note ).toMatch( new RegExp( `href="http://localhost:8888/wp-admin/post\\.php\\?post=${ emailPostId }(&amp;|&#038;)action=edit"` ) );

			// And the notice links to the same screen.
			const noticeLink = page.locator( '#bh-wp-venmo-gateway-reconciliation-email-notice a' );
			await expect( noticeLink ).toHaveAttribute( 'href', `http://localhost:8888/wp-admin/post.php?post=${ emailPostId }&action=edit` );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wc/v3/orders/${ order.id }`,
				params: { force: true },
			} );
		}
	} );
} );
