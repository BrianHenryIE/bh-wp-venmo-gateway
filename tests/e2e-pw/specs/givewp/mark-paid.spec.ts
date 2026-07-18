/**
 * Playwright E2E test for the "Mark paid" action on the GiveWP donations list.
 *
 * A pending Venmo donation is arranged via the test-helper REST endpoint, then
 * an admin marks it paid through the Status-column link + modal, and we assert
 * (via REST) that the donation is completed. Only the modal + AJAX — the part
 * under test — is exercised through the UI.
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { loginAsAdmin } from '../../helpers/general/ui/login';

const HELPER_PATH = '/e2e-test-helper/v1/give/donation';
const CUSTOMER_VENMO_USERNAME = 'brianhenryie';

test.describe( 'Venmo GiveWP donations list – mark paid', () => {
	test( 'admin marks a pending Venmo donation paid via the modal', async ( {
		page,
		admin,
		requestUtils,
	} ) => {
		// Arrange (REST): a pending Venmo donation.
		const created = await requestUtils.rest( {
			method: 'POST',
			path: HELPER_PATH,
			data: { status: 'pending', gateway: 'venmo' },
		} );
		const donationId = String( created.id );

		try {
			// Act (UI): open the donations list and mark this donation paid.
			await loginAsAdmin( page );
			await admin.visitAdminPage(
				'edit.php',
				'post_type=give_forms&page=give-payment-history'
			);

			// TEMP DIAGNOSTIC: dump the list state + donation record to find why the
			// mark-paid link is absent in CI.
			const restState = await requestUtils.rest( {
				method: 'GET',
				path: `${ HELPER_PATH }?id=${ donationId }`,
			} );
			// eslint-disable-next-line no-console
			console.log( 'DIAG rest:', JSON.stringify( restState ) );
			const listState = await page.evaluate( () => {
				const table = document.querySelector( '.wp-list-table' );
				return {
					url: location.href,
					markLinks: document.querySelectorAll(
						'.bh-venmo-mark-paid'
					).length,
					bodyRows: document.querySelectorAll(
						'.wp-list-table tbody tr'
					).length,
					tableText: table
						? ( table.textContent || '' )
								.replace( /\s+/g, ' ' )
								.slice( 0, 600 )
						: 'NO .wp-list-table; body: ' +
						  ( document.body.textContent || '' )
								.replace( /\s+/g, ' ' )
								.slice( 0, 600 ),
				};
			} );
			// eslint-disable-next-line no-console
			console.log( 'DIAG list:', JSON.stringify( listState ) );

			// The "Mark paid" link lives in a WordPress row-actions block that WP
			// positions off-screen until its row is hovered. Synthetic hover is
			// unreliable across CI's headless browsers, so reveal the row-actions with
			// a style override and click the link directly (deterministic everywhere).
			const markPaidLink = page.locator(
				`.bh-venmo-mark-paid[data-donation-id="${ donationId }"]`
			);
			// TEMP: guaranteed-visible diagnostic if the link is missing in CI.
			if ( ( await markPaidLink.count() ) === 0 ) {
				throw new Error(
					`DIAG link absent. rest=${ JSON.stringify(
						restState
					) } list=${ JSON.stringify( listState ) }`
				);
			}
			await markPaidLink.waitFor( {
				state: 'attached',
				timeout: 15_000,
			} );
			await page.addStyleTag( {
				content:
					'.row-actions { position: static !important; left: auto !important; }',
			} );
			await markPaidLink.scrollIntoViewIfNeeded();
			await markPaidLink.click();

			const modal = page.locator( '#bh-venmo-mark-paid-modal' );
			await expect( modal ).toBeVisible();

			// The date field defaults to today's date; the time field defaults to blank.
			await expect(
				modal.locator( '#bh-venmo-payment-date' )
			).not.toHaveValue( '' );
			await expect(
				modal.locator( '#bh-venmo-payment-time' )
			).toHaveValue( '' );

			// Fill the (optional) fields and submit.
			await modal
				.locator( '#bh-venmo-username' )
				.fill( CUSTOMER_VENMO_USERNAME );
			await modal
				.locator( '#bh-venmo-transaction-id' )
				.fill( '1234567890' );
			await modal.locator( '#bh-venmo-payment-time' ).fill( '14:30' );
			await modal.getByRole( 'button', { name: 'Mark paid' } ).click();

			// Assert (REST): the donation is now complete ('publish'). Poll so we do
			// not race the JS page reload.
			await expect
				.poll(
					async () => {
						const donation = await requestUtils.rest( {
							method: 'GET',
							path: `${ HELPER_PATH }?id=${ donationId }`,
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
			).toHaveAttribute(
				'href',
				new RegExp( `view=view-payment-details.*id=${ donationId }` )
			);
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: HELPER_PATH,
				data: { id: created.id },
			} );
		}
	} );
} );
