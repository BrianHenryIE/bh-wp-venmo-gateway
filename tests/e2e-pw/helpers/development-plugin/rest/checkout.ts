/**
 * External dependencies
 */
import * as fs from 'fs';
import * as path from 'path';

import { Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {getSetting} from "../../general/rest/settings";
import {getPostContentRendered, setPageContent} from "../../general/rest/wp-post";

export type CheckoutType = 'blocks' | 'shortcode';

async function getCheckoutPostId(): Promise< number > {
	// woocommerce_checkout_page_id
	const postId = await getSetting( 'woocommerce_checkout_page_id' );
	return parseInt( postId );
}

async function getCheckoutPageContent(): Promise< string > {
	const pageId = await getCheckoutPostId();
	return await getPostContentRendered( 'page', pageId );
}

async function setCheckoutPageContent( postContent: string ) {
	const page_id = await getCheckoutPostId();
	await setPageContent( page_id, postContent );
}

export async function useBlocksCheckout() {
	const contentPath = path.join(
		__dirname,
		'../../../../_wp-env/blocks-checkout-post-content.txt'
	);
	const postContent = fs.readFileSync( contentPath, 'utf8' );
	await setCheckoutPageContent( postContent );
}

export async function useShortcodeCheckout() {
	const postContent =
		'<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->';
	await setCheckoutPageContent( postContent );
}

export async function detectCheckoutType(): Promise< CheckoutType > {
	const postContent = await getCheckoutPageContent();

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

export async function isBlocksCheckout(): Promise< boolean > {
	return ( await detectCheckoutType() ) === 'blocks';
}

export async function isShortcodeCheckout(): Promise< boolean > {
	return ( await detectCheckoutType() ) === 'shortcode';
}
