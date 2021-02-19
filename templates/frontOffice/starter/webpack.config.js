require('dotenv').config();
const path = require('path');
const Encore = require('@symfony/webpack-encore');
const SVGSpritemapPlugin = require('svg-spritemap-webpack-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');
const chokidar = require('chokidar');

const publicPath = Encore.isDevServer()
	? `${process.env.BROWSERSYNC_PROXY}/dist/`
	: '/dist/';

// ENTRIES
Encore.addEntry('app', './assets/js/app.js')
	.addEntry('home', './assets/js/routes/home')
	.addEntry('category', './assets/js/routes/category')
	.addEntry('product', './assets/js/routes/product')
	.addEntry('register', './assets/js/routes/register')
	.addEntry('address', './assets/js/routes/address')
	.addEntry('page-cookies', './assets/js/routes/page-cookies')
	.addEntry('delivery', './assets/js/routes/delivery');

if (!Encore.isRuntimeEnvironmentConfigured()) {
	Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

// GENERAL
Encore.configureFriendlyErrorsPlugin((options) => {
	options.clearConsole = true;
});

// JS CONFIG
Encore.setOutputPath('dist')
	.setPublicPath(publicPath)
	.addAliases({
		'@components': path.resolve(__dirname, './components'),
		'@js': path.resolve(__dirname, './assets/js'),
		'@redux': path.resolve(__dirname, './assets/js/redux'),
		'@utils': path.resolve(__dirname, './assets/js/utils'),
		'@css': path.resolve(__dirname, './assets/css'),
		'@images': path.resolve(__dirname, './assets/images'),
		'@icons': path.resolve(__dirname, './assets/svg-icons'),
		'@fonts': path.resolve(__dirname, './assets/fonts'),
		'@favicons': path.resolve(__dirname, './assets/favicons')
	})
	.splitEntryChunks()
	.enableReactPreset()
	.enableSingleRuntimeChunk()
	.enableSourceMaps(!Encore.isProduction())
	.enableVersioning(Encore.isProduction())
	.configureBabel((config) => {
		config.plugins.push('@babel/plugin-transform-runtime');
	})
	.addPlugin(new ESLintPlugin());

// SERVER CONFIG
Encore.configureDevServerOptions((options) => {
	options.headers = {
		'Access-Control-Allow-Origin': '*'
	};
	options.overlay = true;
	options.contentBase = './dist';
	options.serveIndex = true;
	options.port = 8081;

	options.after = (app, server) => {
		chokidar
			.watch(['./**/*.html', './**/*.tpl'], { ignoreInitial: true })
			.on('all', function () {
				server.sockWrite(server.sockets, 'content-changed');
			});
	};
});

Encore.setManifestKeyPrefix('dist/');

// CSS CONFIG
Encore.enablePostCssLoader();

// IMAGES CONFIG
Encore.disableImagesLoader()
	.copyFiles({
		from: './assets/images',
		to: 'images/[path][name].[ext]',
		pattern: /\.(png|jpg|jpeg|gif|svg|webp)$/
	})
	.copyFiles({
		from: './assets/favicons',
		to: 'favicons/[path][name].[ext]',
		pattern: /\.(png|jpg|jpeg|gif|ico|svg|webp|webmanifest)$/
	})
	.addLoader({
		test: /\.svg$/,
		issuer: {
			test: /\.js$/
		},
		use: [
			{
				loader: '@svgr/webpack'
			},
			'file-loader'
		]
	})
	.addLoader({
		test: /\.(png|jpg|jpeg|gif|ico|webp)$/,
		loader: 'url-loader',
		options: { name: 'images/[name].[hash:8].[ext]', publicPath: '/dist/' }
	})
	.addPlugin(
		new SVGSpritemapPlugin(path.join(process.cwd(), 'assets/svg-icons/*?svg'), {
			output: {
				filename: 'sprite.[contenthash].svg',
				chunk: {
					name: 'sprite',
					keep: true
				},
				svg4everybody: false
			},
			sprite: {
				prefix: 'svg-icons-'
			}
		})
	);

module.exports = Encore.getWebpackConfig();
