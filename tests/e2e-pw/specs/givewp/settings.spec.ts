/**
 * Playwright E2E test for the Venmo GiveWP gateway settings screen.
 *
 * Verifies the store "Venmo @username" field renders on the gateway settings
 * page and reflects the saved option value. The field is registered via the
 * `give_get_settings_gateways` filter (Gateway_Settings::register_settings).
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../../helpers/general/ui/login';

const STORE_VENMO_USERNAME = 'testvendor'; // seeded in initialize-internal.sh

test.describe( 'Venmo GiveWP gateway settings', () => {

	test( 'store @username field is shown and holds the saved value', async ( { admin, page } ) => {
		await loginAsAdmin( page );

		await admin.visitAdminPage(
			'edit.php',
			'post_type=give_forms&page=give-settings&tab=gateways&section=venmo'
		);

		// GiveWP renders a `text` setting as <input id="venmo_store_username" …>.
		const field = page.locator( '#venmo_store_username' );
		await expect( field ).toBeVisible();
		await expect( field ).toHaveValue( STORE_VENMO_USERNAME );
	} );

	test( 'shows the actual date of the most-recent Venmo donation per status', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		// Arrange a pending Venmo donation dated far in the future so it is the
		// most-recent pending donation. The distinctive year (2035) can never be
		// the current clock, so it proves the donation's own date is displayed
		// (a regression guard against showing the current time instead).
		const created = await requestUtils.rest( {
			method: 'POST',
			path: '/e2e-test-helper/v1/give/donation',
			data: { status: 'pending', date: '2035-06-15 08:30:00' },
		} );

		try {
			await loginAsAdmin( page );
			await admin.visitAdminPage(
				'edit.php',
				'post_type=give_forms&page=give-settings&tab=gateways&section=venmo'
			);

			const summary = page.locator( '.bh-venmo-last-donations' );
			await expect( summary ).toBeVisible();
			await expect( summary ).toContainText( 'Most recent Venmo donation' );

			// The Pending line must show the donation's own (future) date.
			await expect(
				summary.locator( 'li', { hasText: 'Pending:' } )
			).toContainText( '2035' );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: '/e2e-test-helper/v1/give/donation',
				data: { id: created.id },
			} );
		}
	} );
} );
