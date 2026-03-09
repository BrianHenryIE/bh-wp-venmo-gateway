const defaultConfig                                = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

const wcDepMap = {
	'@woocommerce/blocks-registry': [ 'wc', 'wcBlocksRegistry' ],
	'@woocommerce/settings': [ 'wc', 'wcSettings' ],
};

const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings': 'wc-settings',
};

const requestToExternal = ( request ) => {
	if ( wcDepMap[ request ] ) {
		return wcDepMap[ request ];
	}
};

const requestToHandle = ( request ) => {
	if ( wcHandleMap[ request ] ) {
		return wcHandleMap[ request ];
	}
};

module.exports = {
	...defaultConfig,
	entry: {
		'checkout/index': './src/checkout/index.tsx',
	},
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
			plugin.constructor.name !== 'DependencyExtractionWebpackPlugin' &&
				plugin.constructor.name !== 'CleanWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(
			{
					requestToExternal,
					requestToHandle,
			}
		),
	],
};
