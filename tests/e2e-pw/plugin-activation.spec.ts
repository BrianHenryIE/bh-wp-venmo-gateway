/**
 * Playwright E2E test for the Venmo Gateway plugin.
 *
 * Verifies the plugin is active and visible on the plugins page.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Plugin activation', () => {
	test( 'plugin is listed on the plugins page', async ( { admin, page } ) => {
		await admin.visitAdminPage( 'plugins.php' );

		// Check the plugin row exists
		const pluginRow = page.locator( 'tr[data-slug="bh-wc-venmo-gateway"]' );
		await expect( pluginRow ).toBeVisible();

		// Verify it's active (has "Deactivate" link)
		const deactivateLink = pluginRow.locator( 'a:has-text("Deactivate")' );
		await expect( deactivateLink ).toBeVisible();
	} );
} );