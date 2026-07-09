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
} );
