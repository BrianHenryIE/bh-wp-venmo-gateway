/**
 * Playwright E2E test for the Venmo GiveWP gateway settings screen.
 *
 * Verifies the store "Venmo @username" field renders on the gateway settings
 * page and reflects the saved option value, and that the plugin-wide log level
 * (shared with the WooCommerce gateway) can be configured there. The fields are
 * registered via the `give_get_settings_gateways` filter (Gateway_Settings::register_settings).
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../../helpers/general/ui/login';

const STORE_VENMO_USERNAME = 'testvendor'; // seeded in initialize-internal.sh

const LOG_LEVEL_OPTION = 'bh_wp_venmo_gateway_log_level'; // exposed via the development plugin's /wp/v2/settings.
const GIVE_VENMO_SETTINGS_PATH = 'post_type=give_forms&page=give-settings&tab=gateways&section=venmo';

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

	test( 'log level select and logs page link are shown', async ( { admin, page } ) => {
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'edit.php', GIVE_VENMO_SETTINGS_PATH );

		// GiveWP renders a `select` setting as <select id="venmo_log_level" …>.
		const select = page.locator( 'select#venmo_log_level' );
		await expect( select ).toBeVisible();
		await expect( select.locator( 'option' ) ).toHaveText( [ 'None', 'Error', 'Warning', 'Notice', 'Info', 'Debug' ] );

		const logsLink = page.locator( 'a', { hasText: 'View Logs' } );
		await expect( logsLink ).toBeVisible();
		await expect( logsLink ).toHaveAttribute( 'href', /admin\.php\?page=bh-wp-venmo-gateway-logs/ );
	} );

	test( 'log level is shared with the plugin-wide option in both directions', async ( {
		admin,
		page,
		requestUtils,
	}, testInfo ) => {
		// The option is global to the site, so concurrent runs in other browsers would race
		// each other's arrange/restore steps. The save behaviour is not browser-specific.
		test.skip( testInfo.project.name !== 'chromium', 'Mutates a site-wide option; run in one browser only.' );

		const original = ( await requestUtils.rest( { path: '/wp/v2/settings' } ) )[ LOG_LEVEL_OPTION ];

		try {
			// Arrange: a value set elsewhere (e.g. the WooCommerce gateway settings page)
			// must be reflected in the GiveWP select.
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/settings',
				data: { [ LOG_LEVEL_OPTION ]: 'error' },
			} );

			await loginAsAdmin( page );
			await admin.visitAdminPage( 'edit.php', GIVE_VENMO_SETTINGS_PATH );

			const select = page.locator( 'select#venmo_log_level' );
			await expect( select ).toHaveValue( 'error' );

			// Act: save a new value from the GiveWP page.
			await select.selectOption( 'debug' );
			await page.locator( 'input[name="save"]' ).click();
			await page.waitForLoadState( 'domcontentloaded' );

			// Assert: the plugin-wide option was updated.
			const settings = await requestUtils.rest( { path: '/wp/v2/settings' } );
			expect( settings[ LOG_LEVEL_OPTION ] ).toBe( 'debug' );
		} finally {
			await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/settings',
				data: { [ LOG_LEVEL_OPTION ]: original ?? 'notice' },
			} );
		}
	} );
} );
