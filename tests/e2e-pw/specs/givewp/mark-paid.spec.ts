/**
 * Playwright E2E test for the "Mark paid" action on the GiveWP donations list.
 *
 * A pending Venmo donation is created via the legacy form, then an admin marks
 * it paid through the Status-column link + modal, and we assert the donation
 * becomes complete.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../../helpers/general/ui/login';

const CUSTOMER_VENMO_USERNAME = 'brianhenryie';

test.describe( 'Venmo GiveWP donations list – mark paid', () => {

	test.describe.configure( { mode: 'serial' } );
	test.setTimeout( 120_000 );

	test( 'admin marks a pending Venmo donation paid via the modal', async ( { page, admin, requestUtils } ) => {
		// Arrange: create a pending Venmo donation through the legacy form.
		await page.context().clearCookies();
		await page.goto( '/donate/' );
		await page.fill( '#give-first', 'Mark' );
		await page.fill( '#give-last', 'Paid' );
		await page.fill( '#give-email', 'markpaid@example.com' );
		await page.fill( '#give-venmo-username', CUSTOMER_VENMO_USERNAME );
		await page.click( '#give-purchase-button' );
		await page.waitForURL( /donation-confirmation/, { timeout: 60_000 } );

		// Act: open the donations list as admin. The newest donation is the first
		// row, so its "Mark paid" link is the first on the page.
		await loginAsAdmin( page );
		await admin.visitAdminPage( 'edit.php', 'post_type=give_forms&page=give-payment-history' );

		const markPaidLink = page.locator( '.bh-venmo-mark-paid' ).first();
		await expect( markPaidLink ).toBeVisible();
		const donationId = await markPaidLink.getAttribute( 'data-donation-id' );
		expect( donationId ).toBeTruthy();

		await markPaidLink.click();

		const modal = page.locator( '#bh-venmo-mark-paid-modal' );
		await expect( modal ).toBeVisible();

		// The date field defaults to today's date; the time field defaults to blank.
		await expect( modal.locator( '#bh-venmo-payment-date' ) ).not.toHaveValue( '' );
		await expect( modal.locator( '#bh-venmo-payment-time' ) ).toHaveValue( '' );

		// Fill the (optional) fields and submit.
		await modal.locator( '#bh-venmo-username' ).fill( CUSTOMER_VENMO_USERNAME );
		await modal.locator( '#bh-venmo-transaction-id' ).fill( '1234567890' );
		await modal.locator( '#bh-venmo-payment-time' ).fill( '14:30' );
		await modal.getByRole( 'button', { name: 'Mark paid' } ).click();

		// Assert: the donation is now complete ('publish'). Poll the REST API so
		// we do not race the JS page reload.
		await expect
			.poll(
				async () => {
					const donation = await requestUtils.rest( {
						method: 'GET',
						path: `/givewp/v3/donations/${ donationId }`,
					} );
					return donation.status;
				},
				{ timeout: 30_000 }
			)
			.toBe( 'publish' );

		// After the redirect, a success notice names the donation and its
		// details, and links to the single donation view.
		const notice = page.locator( '.notice-success', {
			hasText: `Venmo donation #${ donationId } `,
		} );
		await expect( notice ).toBeVisible();
		await expect( notice ).toContainText( 'marked paid' );
		await expect( notice ).toContainText( '@brianhenryie' );
		await expect( notice ).toContainText( '1234567890' );
		await expect(
			notice.getByRole( 'link', { name: 'View donation' } )
		).toHaveAttribute( 'href', new RegExp( `view=view-payment-details.*id=${ donationId }` ) );
	} );
} );
