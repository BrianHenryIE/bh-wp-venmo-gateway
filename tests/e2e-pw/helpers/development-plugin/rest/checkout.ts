/**
 * External dependencies
 */
import * as fs from 'fs';
import * as path from 'path';

import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {getSetting} from "../../general/rest/settings";
import {getPostContentRendered, setPageContent} from "../../general/rest/wp-post";

export type CheckoutType = 'blocks' | 'shortcode';

/**
 * Dedicated checkout pages — one per checkout style — created in
 * initialize-internal.sh. Tests navigate to these instead of mutating the single
 * shared /checkout/ page, so the shortcode and blocks specs can run in parallel.
 */
export const SHORTCODE_CHECKOUT_PATH = '/checkout-shortcode/';
export const BLOCKS_CHECKOUT_PATH = '/checkout-blocks/';

async function getCheckoutPostId( requestUtils: RequestUtils ): Promise< number > {
	// woocommerce_checkout_page_id
	const postId = await getSetting( requestUtils, 'woocommerce_checkout_page_id' );
	return parseInt( postId );
}

async function getCheckoutPageContent( requestUtils: RequestUtils ): Promise< string > {
	const pageId = await getCheckoutPostId( requestUtils );
	return await getPostContentRendered( 'page', pageId );
}

async function setCheckoutPageContent( requestUtils: RequestUtils, postContent: string ) {
	const page_id = await getCheckoutPostId( requestUtils );
	await setPageContent( requestUtils, page_id, postContent );
}

export async function useBlocksCheckout( requestUtils: RequestUtils ) {
	const contentPath = path.join(
		__dirname,
		'../../../../_wp-env/blocks-checkout-post-content.txt'
	);
	const postContent = fs.readFileSync( contentPath, 'utf8' );
	await setCheckoutPageContent( requestUtils, postContent );
}

export async function useShortcodeCheckout( requestUtils: RequestUtils ) {
	const postContent =
		'<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->';
	await setCheckoutPageContent( requestUtils, postContent );
}

export async function detectCheckoutType( requestUtils: RequestUtils ): Promise< CheckoutType > {
	const postContent = await getCheckoutPageContent( requestUtils );

	// Check for blocks checkout indicators
	const blocksCheckoutStrings = [
		'wc-block-checkout',
		'wp-block-woocommerce-checkout',
		'wc-block-components-checkout-place-order-button',
	];

	// Test for blocks checkout
	for ( const htmlString of blocksCheckoutStrings ) {
		if ( postContent.includes( htmlString ) ) {
			return 'blocks';
		}
	}

	// Check for shortcode checkout indicators
	const shortcodeCheckoutElements = [
		'[woocommerce_checkout]',
		'.woocommerce-checkout',
		'#place_order',
		'form[name="checkout"]',
	];

	// Test for shortcode checkout
	for ( const htmlString of shortcodeCheckoutElements ) {
		if ( postContent.includes( htmlString ) ) {
			return 'shortcode';
		}
	}

	// TODO: Maybe throw error if neither detected?
	// Default to shortcode if uncertain
	return 'shortcode';
}

export async function isBlocksCheckout( requestUtils: RequestUtils ): Promise< boolean > {
	return ( await detectCheckoutType( requestUtils ) ) === 'blocks';
}

export async function isShortcodeCheckout( requestUtils: RequestUtils ): Promise< boolean > {
	return ( await detectCheckoutType( requestUtils ) ) === 'shortcode';
}
