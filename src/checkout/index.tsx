/**
 * WooCommerce Blocks Checkout integration for the Venmo payment gateway.
 *
 * Registers the Venmo payment method with the WooCommerce Blocks checkout,
 * including a text input for the customer's Venmo username.
 *
 * @package brianhenryie/bh-wc-venmo-gateway
 */

/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { useState, useCallback, useEffect } from '@wordpress/element';

interface VenmoGatewaySettings {
	title?: string;
	description?: string;
	supports?: string[];
	venmo_icon_url?: string;
}

interface PaymentMethodLabelProps {
	components: {
		PaymentMethodLabel: React.FC< { text: string } >;
		PaymentMethodIcons: React.FC< { icons: Array< { id: string; src: string; alt: string } > } >;
	};
}

interface EmitResponseType {
	responseTypes: {
		SUCCESS: string;
		ERROR: string;
		FAIL: string;
	};
	noticeContexts: {
		PAYMENTS: string;
	};
}

interface PaymentMethodContentProps {
	eventRegistration: {
		onPaymentSetup: ( callback: () => { type: string; meta?: { paymentMethodData: Record< string, string > }; message?: string } ) => () => void;
	};
	emitResponse: EmitResponseType;
}

const settings: VenmoGatewaySettings = getSetting( 'venmo_data', {} );

var label = '';
const settingsTitle: string = decodeEntities( settings.title || '' );
if(settingsTitle !== '' && settingsTitle !== 'Venmo' && settingsTitle !== 'venmo' ) {
	label = settingsTitle;
}

/**
 * Content component — shows description and Venmo username input.
 */
const VenmoContent: React.FC< PaymentMethodContentProps > = ( {
	eventRegistration,
	emitResponse,
} ) => {
	const [ venmoUsername, setVenmoUsername ] = useState( '' );
	const { onPaymentSetup } = eventRegistration;

	const onUsernameChange = useCallback(
		( e: React.ChangeEvent< HTMLInputElement > ) => {
			setVenmoUsername( e.target.value );
		},
		[]
	);

	useEffect( () => {
		const unsubscribe = onPaymentSetup( () => {
			if ( ! venmoUsername.trim() ) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: __(
						'Please enter your Venmo username.',
						'bh-wc-venmo-gateway'
					),
				};
			}
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						'_customer-venmo-username': venmoUsername.trim(),
					},
				},
			};
		} );
		return unsubscribe;
	}, [ venmoUsername, onPaymentSetup, emitResponse.responseTypes ] );

	return (
		<div className="bh-wc-venmo-gateway-blocks-checkout">
			{ settings.description && (
				<p className="wc-block-components-checkout-step__description">
					{ decodeEntities( settings.description ) }
				</p>
			) }
			<div className="bh-wc-venmo-gateway-username-field">
				<label htmlFor="venmo-username-input">
					{ __( 'Enter your Venmo username:', 'bh-wc-venmo-gateway' ) }
					<abbr className="required" title="required">*</abbr>
				</label>
				<input
					id="venmo-username-input"
					type="text"
					value={ venmoUsername }
					onChange={ onUsernameChange }
					placeholder={ __( 'Venmo username', 'bh-wc-venmo-gateway' ) }
					maxLength={ 255 }
					required
					className="wc-block-components-text-input"
					aria-label={ __( 'Venmo username', 'bh-wc-venmo-gateway' ) }
				/>
			</div>
		</div>
	);
};

/**
 * Label component with optional icon.
 */
const VenmoLabel: React.FC< PaymentMethodLabelProps > = ( { components } ) => {
	const { PaymentMethodLabel } = components;
	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
			{ settings.venmo_icon_url && (
				<img
					src={ settings.venmo_icon_url }
					alt={ label }
					style={ { height: '24px' } }
				/>
			) }
			<PaymentMethodLabel text={ label } />
		</div>
	);
};

/**
 * Edit component for the block editor — static preview.
 */
const VenmoEdit: React.FC = () => {
	return (
		<div className="bh-wc-venmo-gateway-blocks-checkout">
			{ settings.description && (
				<p className="wc-block-components-checkout-step__description">
					{ decodeEntities( settings.description || '' ) }
				</p>
			) }
			<div className="bh-wc-venmo-gateway-username-field">
				<label htmlFor="venmo-username-input-preview">
					{ __( 'Enter your Venmo username:', 'bh-wc-venmo-gateway' ) }
					<abbr className="required" title="required">*</abbr>
				</label>
				<input
					id="venmo-username-input-preview"
					type="text"
					placeholder={ __( 'Venmo username', 'bh-wc-venmo-gateway' ) }
					disabled
					className="wc-block-components-text-input"
				/>
			</div>
		</div>
	);
};

const venmoPaymentMethod = {
	name: 'venmo',
	label: <VenmoLabel components={ { PaymentMethodLabel: ( { text }: { text: string } ) => <>{ text }</>, PaymentMethodIcons: () => null } } />,
	content: <VenmoContent eventRegistration={ { onPaymentSetup: () => () => {} } } emitResponse={ { responseTypes: { SUCCESS: 'success', ERROR: 'error', FAIL: 'fail' }, noticeContexts: { PAYMENTS: 'wc/checkout/payments' } } } />,
	edit: <VenmoEdit />,
	canMakePayment: (): boolean => true,
	ariaLabel: label,
	supports: {
		features: settings.supports || [],
	},
};

registerPaymentMethod( venmoPaymentMethod );
