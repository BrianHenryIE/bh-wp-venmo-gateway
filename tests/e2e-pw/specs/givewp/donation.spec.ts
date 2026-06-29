import { test, expect } from '@wordpress/e2e-test-utils-playwright';

const CUSTOMER_VENMO_USERNAME = 'brianhenryie';
const STORE_VENMO_USERNAME    = 'testvendor'; // set once in initialize-internal.sh

// ── Legacy (v2) form ──────────────────────────────────────────────────────
//
// The legacy form renders via PHP (getLegacyFormFieldMarkup). When Venmo is
// the only enabled gateway, GiveWP auto-selects it — no gateway radio shown.
// `_give_show_register_form = 'none'` makes GiveWP render the donor info
// section (first/last name, email) which the server-side handler requires.

test.describe( 'Venmo GiveWP donation – legacy form', () => {

	test.describe.configure( { mode: 'serial' } );
	test.setTimeout( 90_000 );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
	} );

	test( 'Venmo gateway fields are shown', async ( { page } ) => {
		await page.goto( '/donate/' );
		await expect( page.locator( '#give-venmo-gateway-fields' ) ).toBeVisible();
		await expect( page.locator( '#give-venmo-username' ) ).toBeVisible();
	} );

	test( 'can complete a donation', async ( { page } ) => {
		await page.goto( '/donate/' );
		await page.fill( '#give-first', 'Test' );
		await page.fill( '#give-last', 'Donor' );
		await page.fill( '#give-email', 'test@example.com' );
		await page.fill( '#give-venmo-username', CUSTOMER_VENMO_USERNAME );
		await page.click( '#give-purchase-button' );
		await page.waitForURL( /donation-confirmation/, { timeout: 60_000 } );
	} );

	test( 'Venmo username field is required', async ( { page } ) => {
		await page.goto( '/donate/' );
		await page.fill( '#give-first', 'Test' );
		await page.fill( '#give-last', 'Donor' );
		await page.fill( '#give-email', 'test@example.com' );
		// HTML5 required attribute on #give-venmo-username blocks submission.
		await page.click( '#give-purchase-button' );
		await page.waitForTimeout( 2_000 );
		expect( page.url() ).not.toMatch( /donation-confirmation/ );
	} );

	// The donation-confirmation page cannot be loaded directly without the
	// context of a fresh donation, so we must complete a real donation to reach
	// it. The Venmo donation is `pending`, so the page shows the payment QR code.
	test( 'donation confirmation page shows the Venmo payment QR code', async ( { page } ) => {
		await page.goto( '/donate/' );
		await page.fill( '#give-first', 'Test' );
		await page.fill( '#give-last', 'Donor' );
		await page.fill( '#give-email', 'test@example.com' );
		await page.fill( '#give-venmo-username', CUSTOMER_VENMO_USERNAME );
		await page.click( '#give-purchase-button' );
		await page.waitForURL( /donation-confirmation/, { timeout: 60_000 } );

		const qrImage = page.locator( 'img[alt="Payment QR code"]' );
		await expect( qrImage ).toBeVisible();
		// Confirm it is an actually-rendered inline QR, not a broken/empty src.
		await expect( qrImage ).toHaveAttribute( 'src', /^data:image\// );

		// The generic "currently processing" notice is replaced with Venmo
		// instructions naming the amount ($25, set in initialize-internal.sh), and
		// "$25 via Venmo to @testvendor" links to the Venmo payment URL.
		await expect(
			page.locator( 'text=Your donation is currently processing' )
		).toHaveCount( 0 );

		const payLink = page.getByRole( 'link', {
			name: `$25 via Venmo to @${ STORE_VENMO_USERNAME }`,
			exact: true,
		} );
		await expect( payLink ).toBeVisible();
		await expect( payLink ).toHaveAttribute( 'href', /venmo\.com\/testvendor/ );
	} );

	test( 'donation shows pending status in admin', async ( { page, requestUtils } ) => {
		await page.goto( '/donate/' );
		await page.fill( '#give-first', 'Test' );
		await page.fill( '#give-last', 'Donor' );
		await page.fill( '#give-email', 'test@example.com' );
		await page.fill( '#give-venmo-username', CUSTOMER_VENMO_USERNAME );
		await page.click( '#give-purchase-button' );
		await page.waitForURL( /donation-confirmation/, { timeout: 60_000 } );

		const donations = await requestUtils.rest( {
			method: 'GET',
			path: '/givewp/v3/donations',
			data: { perPage: 1, sortColumn: 'id', sortDirection: 'desc' },
		} );
		expect( donations[ 0 ] ).toMatchObject( {
			status: 'pending',
			gatewayId: 'venmo',
		} );
	} );
} );

// ── Modern (v3 / Sequoia) form ─────────────────────────────────────────────
//
// The v3 form renders inside an <iframe> via GiveWP's React form builder.
// After a successful donation, GiveWP navigates the IFRAME (not the parent
// page) to /?givewp-route=donation-confirmation-receipt-view&receipt-id=…
// so we assert on content inside the frame, not on page.waitForURL().

test.describe( 'Venmo GiveWP donation – v3 Sequoia form', () => {

	test.describe.configure( { mode: 'serial' } );
	test.setTimeout( 90_000 );

	test.beforeEach( async ( { page } ) => {
		await page.context().clearCookies();
	} );

	test( 'Venmo gateway fields are shown', async ( { page } ) => {
		await page.goto( '/donate-v3/' );
		const frame = page.frameLocator( 'iframe' );
		await expect( frame.locator( '#give-venmo-username' ) ).toBeVisible();
	} );

	test( 'can complete a donation', async ( { page } ) => {
		await page.goto( '/donate-v3/' );
		const frame = page.frameLocator( 'iframe' );
		await frame.getByPlaceholder( 'John' ).fill( 'Test' );
		await frame.getByPlaceholder( 'Doe' ).fill( 'Donor' );
		await frame.getByLabel( 'Email Address *' ).fill( 'test@example.com' );
		await frame.locator( '#give-venmo-username' ).fill( CUSTOMER_VENMO_USERNAME );
		await frame.getByRole( 'button', { name: 'Donate now' } ).click();
		await expect( frame.getByText( 'Success!' ) ).toBeVisible( { timeout: 60_000 } );
	} );

	test( 'confirmation receipt shows the Venmo payment QR code', async ( { page } ) => {
		await page.goto( '/donate-v3/' );
		const frame = page.frameLocator( 'iframe' );
		await frame.getByPlaceholder( 'John' ).fill( 'Test' );
		await frame.getByPlaceholder( 'Doe' ).fill( 'Donor' );
		await frame.getByLabel( 'Email Address *' ).fill( 'test@example.com' );
		await frame.locator( '#give-venmo-username' ).fill( CUSTOMER_VENMO_USERNAME );
		await frame.getByRole( 'button', { name: 'Donate now' } ).click();
		await expect( frame.getByText( 'Success!' ) ).toBeVisible( { timeout: 60_000 } );

		// While pending, the QR code replaces the celebratory "thanks for your
		// donation" heading. The receipt React app mounts a moment after "Success!".
		const qrImage = frame.locator( 'img[alt="Payment QR code"]' );
		await expect( qrImage ).toBeVisible( { timeout: 15_000 } );
		await expect( qrImage ).toHaveAttribute( 'src', /^data:image\// );

		// The "Payment Pending" detail links to the Venmo payment. The v3 form's
		// donation amount is $10 (set in its blocks.json default).
		await expect(
			frame.getByText( `$10 via Venmo to @${ STORE_VENMO_USERNAME }` )
		).toBeVisible();

		// The pending receipt must not thank the donor as if payment is complete.
		await expect(
			frame.getByText( 'thanks for your donation', { exact: false } )
		).toHaveCount( 0 );
	} );

	test( 'Venmo username field is required', async ( { page } ) => {
		await page.goto( '/donate-v3/' );
		const frame = page.frameLocator( 'iframe' );
		await frame.getByPlaceholder( 'John' ).fill( 'Test' );
		await frame.getByPlaceholder( 'Doe' ).fill( 'Donor' );
		await frame.getByLabel( 'Email Address *' ).fill( 'test@example.com' );
		// Do not fill venmo username — submit should fail.
		await frame.getByRole( 'button', { name: 'Donate now' } ).click();
		await page.waitForTimeout( 3_000 );
		expect( page.url() ).not.toMatch( /donation-confirmation|receipt/ );
	} );
} );
