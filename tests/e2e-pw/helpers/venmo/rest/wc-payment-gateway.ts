/**
 * External dependencies
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

const GATEWAY_PATH = '/wc/v3/payment_gateways/venmo';

export async function getVenmoUsername( requestUtils: RequestUtils ): Promise< string > {
	const gateway = await requestUtils.rest( { path: GATEWAY_PATH } );
	return gateway.settings.store_venmo_username.value as string;
}

export async function setVenmoUsername( requestUtils: RequestUtils, username: string ): Promise< void > {
	await requestUtils.rest( {
		method: 'PUT',
		path: GATEWAY_PATH,
		data: { settings: { store_venmo_username: username } },
	} );
}
