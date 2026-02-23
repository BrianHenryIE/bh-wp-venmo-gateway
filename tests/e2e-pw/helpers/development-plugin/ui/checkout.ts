/**
 * External dependencies
 */
import * as fs from 'fs';
import * as path from 'path';

import { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { testConfig } from '../../../test-config';
import {detectCheckoutType} from "../rest/checkout";

export async function fillBilling( page: Page ): Promise< void > {
	const billing = testConfig.addresses.customer.billing;
	const checkoutType = await detectCheckoutType();

	if ( checkoutType === 'blocks' ) {
		// Blocks checkout field selectors
		await page.fill( '#email', billing.email );
		await page.fill( '#billing-first_name', billing.firstname );
		await page.fill( '#billing-last_name', billing.lastname );
		// await page.fill('#billing-country', billing.country);

		const billingAddress = await page.locator( '#billing-fields' );
		await billingAddress
			.getByLabel( 'Country/Region' )
			.selectOption( billing.country );
		// await billingAddress.getByLabel('Country/Region').click();
		// await billingAddress.getByLabel('Country/Region').fill('united');
		// await billingAddress.getByLabel('United States (US)', { exact: true }).click();

		// await page.waitForLoadState( 'networkidle' );

		await page.fill( '#billing-address_1', billing.addressfirstline );
		await page.fill( '#billing-address_2', billing.addresssecondline );
		await page.fill( '#billing-city', billing.city );

		// await page.fill('#billing-state', billing.state);
		await billingAddress
			.getByLabel( 'State' )
			.selectOption( billing.state );

		await page.fill( '#billing-postcode', billing.postcode );
	} else {
		// Shortcode checkout field selectors
		await page.fill( '#billing_first_name', billing.firstname );
		await page.fill( '#billing_last_name', billing.lastname );
		if ( await page.isVisible( '#billing_company' ) ) {
			await page.fill( '#billing_company', billing.company );
		}
		await page.selectOption( '#billing_country', 'US' );
		await page.fill( '#billing_address_1', billing.addressfirstline );
		await page.fill( '#billing_address_2', billing.addresssecondline );
		await page.fill( '#billing_city', billing.city );
		await page.selectOption( '#billing_state', billing.state );
		await page.fill( '#billing_postcode', billing.postcode );
		await page.fill( '#billing_phone', billing.phone );
		await page.fill( '#billing_email', billing.email );
	}

	// Wait for form to update
	// await page.waitForTimeout(2000);
	// await page.waitForLoadState('networkidle');
}

export async function selectPaymentGateway(
	page: Page,
	gatewayId: string
): Promise< void > {
	const checkoutType = await detectCheckoutType();
	if ( checkoutType === 'blocks' ) {
		// await page.click('#radio-control-wc-payment-method-options-bh_bitcoin');
		await page.click(
			'#radio-control-wc-payment-method-options-' + gatewayId + '__label'
		);
	} else {
		await page.click( 'label[for="payment_method_' + gatewayId + '"]' );
	}
	// await page.waitForSelector('.payment_method_bh_bitcoin', { state: 'visible' });
}
