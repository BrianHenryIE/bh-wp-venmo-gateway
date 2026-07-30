( function () {
	'use strict';

	var React = window.React;
	var useState = React.useState;
	var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function ( text ) { return text; };

	// Module-level variables shared between Fields and beforeCreatePayment.
	var gatewaySettings = {};
	var currentVenmoUsername = '';

	var VenmoGateway = {
		id: 'venmo',

		initialize: function () {
			gatewaySettings = this.settings || {};
			currentVenmoUsername = '';
		},

		beforeCreatePayment: function () {
			// Strip any leading "@"; the username is stored and used as the bare handle.
			var username = currentVenmoUsername.trim().replace( /^@+/, '' );
			if ( ! username ) {
				throw new Error( __( 'Please enter your Venmo username.', 'bh-wp-venmo-gateway' ) );
			}
			return { venmoUsername: username };
		},

		Fields: function () {
			var storeUsername = gatewaySettings.storeUsername || '';

			var _state = useState( '' );
			var value = _state[ 0 ];
			var setValue = _state[ 1 ];

			function handleChange( e ) {
				setValue( e.target.value );
				currentVenmoUsername = e.target.value;
			}

			return React.createElement(
				'fieldset',
				{ className: 'give-venmo-gateway-fields', id: 'give-venmo-gateway-fields' },
				React.createElement(
					'p',
					{ className: 'form-row give-venmo-username-row' },
					React.createElement(
						'label',
						{ className: 'give-label', htmlFor: 'give-venmo-username' },
						__( 'Your Venmo @username', 'bh-wp-venmo-gateway' ),
						React.createElement( 'span', { className: 'give-required-indicator' }, '*' )
					),
					React.createElement( 'input', {
						id: 'give-venmo-username',
						type: 'text',
						value: value,
						onChange: handleChange,
						placeholder: '@username',
						required: true,
						className: 'give-input required',
					} )
				),
				storeUsername
					? React.createElement(
						'p',
						{ className: 'give-venmo-instructions' },
						__( 'After submitting, please send payment to', 'bh-wp-venmo-gateway' ),
						' ',
						React.createElement( 'strong', null, '@' + storeUsername ),
						' ',
						__( 'on Venmo.', 'bh-wp-venmo-gateway' )
					  )
					: null
			);
		},
	};

	window.givewp.gateways.register( VenmoGateway );
} )();
