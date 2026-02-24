/**
 * External dependencies
 */
import config from '../../../../../playwright.config';

const gatewayUrl = () => `${ config.use.baseURL }/wp-json/wc/v3/payment_gateways/venmo`;

export async function getVenmoUsername(): Promise< string > {
	const response = await fetch( gatewayUrl() );
	const gateway = await response.json();
	return gateway.settings.store_venmo_username.value as string;
}

export async function setVenmoUsername( username: string ): Promise< void > {
	const response = await fetch( gatewayUrl(), {
		method: 'PUT',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( { settings: { store_venmo_username: username } } ),
	} );

	if ( ! response.ok ) {
		const body = await response.text();
		throw new Error( `Failed to set Venmo username: ${ response.status } ${ body }` );
	}
}
