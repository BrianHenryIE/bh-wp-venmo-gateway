/**
 * Development plugin: mock `https://venmo.com/u/{username}` responses.
 *
 * POST   /wp-json/e2e-test-helper/v1/venmo-profiles            { username, display_name }
 * DELETE /wp-json/e2e-test-helper/v1/venmo-profiles/{username}
 * DELETE /wp-json/e2e-test-helper/v1/venmo-profiles
 */
import { debugFetch } from '../../fetch';

const PATH = 'wp-json/e2e-test-helper/v1/venmo-profiles';

export async function mockVenmoProfile( username: string, displayName: string ): Promise< void > {
	const response = await debugFetch( PATH, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( { username, display_name: displayName } ),
	} );
	if ( ! response.ok ) {
		throw new Error( `Failed to mock Venmo profile: ${ response.status } ${ await response.text() }` );
	}
}

export async function removeMockVenmoProfile( username: string ): Promise< void > {
	await debugFetch( `${ PATH }/${ encodeURIComponent( username ) }`, { method: 'DELETE' } );
}
