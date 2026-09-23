// Load the default @wordpress/scripts config object
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
// const path = require( 'path' );

// Use the defaultConfig but replace the entry and output properties
module.exports = {
	...defaultConfig,
	entry: { script: './src/script.js', fscript: './src/fscript.js' },
	output: {
		filename: '[name].js',
	},
};
