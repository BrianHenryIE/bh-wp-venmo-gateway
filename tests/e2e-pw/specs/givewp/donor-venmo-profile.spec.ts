/**
 * When a Venmo donation is made, a background task fetches the donor's full name from their public Venmo
 * profile (venmo.com/u/{username}) and records it in the donation meta with a note.
 *
 * The profile page is mocked by the development plugin (REST). The donation is made through the legacy form
 * (the only way to reach the gateway's createPayment(), which schedules the task), the WP-Cron event is run
 * with WP-CLI, and the result is asserted through the development plugin's donation endpoint (REST).
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import { mockVenmoProfile, removeMockVenmoProfile } from '../../helpers/development-plugin/rest/venmo-profiles';
import { runDueCronHook } from '../../helpers/general/cli/wp-cli';

const DONOR_VENMO_DISPLAY_NAME = 'Dana Profile Donor';
const CRON_HOOK = 'juiced_venmo_gateway_pro_fetch_donor_venmo_profile';

test.describe( 'Donor Venmo profile name', () => {
	test.setTimeout( 90_000 );

	// Each browser project runs this spec concurrently against the same site, so the mocked username is per
	// project: otherwise one project's teardown removes the mock before another project's cron event has run.
	let donorVenmoUsername: string;

	test.beforeEach( async () => {
		donorVenmoUsername = `e2e-donor-profile-tester-${ test.info().project.name }`;
		await mockVenmoProfile( donorVenmoUsername, DONOR_VENMO_DISPLAY_NAME );
	} );

	test.afterEach( async () => {
		await removeMockVenmoProfile( donorVenmoUsername );
	} );

	test( 'is fetched in the background and saved to the donation meta', async ( { page, requestUtils } ) => {
		await page.context().clearCookies();

		// Act (UI): the minimal step under test – make a donation so createPayment() runs.
		await page.goto( '/donate/' );
		await page.fill( '#give-first', 'Dana' );
		await page.fill( '#give-last', 'Donor' );
		await page.fill( '#give-email', 'dana@example.com' );
		await page.fill( '#give-venmo-username', donorVenmoUsername );
		await page.click( '#give-purchase-button' );
		await page.waitForURL( /donation-confirmation/, { timeout: 60_000 } );

		// Other specs make donations at the same time, so find this test's donation by its Venmo username rather
		// than taking the newest.
		const donations = await requestUtils.rest( {
			method: 'GET',
			path: '/givewp/v3/donations',
			data: { perPage: 20, sortColumn: 'id', sortDirection: 'desc' },
		} );
		let donationId: number | null = null;
		for ( const candidate of donations ) {
			const details = await requestUtils.rest( {
				path: '/e2e-test-helper/v1/give/donation',
				params: { id: candidate.id },
			} );
			if ( details.meta[ '_customer-venmo-username' ] === donorVenmoUsername ) {
				donationId = candidate.id;
				break;
			}
		}
		expect( donationId ).not.toBeNull();

		// Act: run the scheduled background task.
		runDueCronHook( CRON_HOOK );

		// Assert (REST): the display name is on the donation and a note records it.
		const donation = await requestUtils.rest( {
			path: '/e2e-test-helper/v1/give/donation',
			params: { id: donationId },
		} );
		expect( donation.meta[ '_customer-venmo-username' ] ).toBe( donorVenmoUsername );
		expect( donation.meta[ '_customer-venmo-display-name' ] ).toBe( DONOR_VENMO_DISPLAY_NAME );
		expect( donation.notes ).toContain( `Venmo profile @${ donorVenmoUsername } is ${ DONOR_VENMO_DISPLAY_NAME }.` );
	} );
} );
