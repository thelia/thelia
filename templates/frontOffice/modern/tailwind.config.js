const colors = require('tailwindcss/colors');

module.exports = {
	purge: [
		'./**/*.html',
		'.**/*.tpl',
		'/components/**/*.css',
		'!(node_modules)/**/*.js'
	],
	// mode: 'jit',
	darkMode: false,
	theme: {
		extend: {
			colors: {
				main: colors.orange[400],
				'main-dark': colors.orange[600]
			}
		}
	},
	variants: {
		extend: {}
	},
	plugins: []
};
