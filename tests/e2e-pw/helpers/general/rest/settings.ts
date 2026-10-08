/**
 * External dependencies
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

// returns json object of settings
async function getSettings( requestUtils: RequestUtils ): Promise< object > {
	return await requestUtils.rest( { path: '/wp/v2/settings' } );
}

export async function getSetting( requestUtils: RequestUtils, name: string ): Promise< any > {
	const settings = await getSettings( requestUtils );

	return settings[ name ];
}
