require('dotenv').config();
const path = require('path');
const SVGSpritemapPlugin = require('svg-spritemap-webpack-plugin');
const Encore = require('@symfony/webpack-encore');
const ESLintPlugin = require('eslint-webpack-plugin');

const chokidar = require('chokidar');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

const publicPath = '/dist/';

// GENERAL
Encore.configureFriendlyErrorsPlugin((options) => {
  options.clearConsole = true;
});
Encore.configureBabel((config) => {
  config.plugins.push('@babel/plugin-transform-runtime');
});

Encore.addPlugin(new ESLintPlugin());

// ENTRIES
Encore.addEntry('app', './assets/js/app.js')
  .addEntry('home', './assets/js/routes/home')
  .addEntry('category', './assets/js/routes/category')
  .addEntry('product', './assets/js/routes/product')
  .addEntry('register', './assets/js/routes/register')
  .addEntry('address', './assets/js/routes/address')
  .addEntry('delivery', './assets/js/routes/delivery');

Encore.setOutputPath('dist/')
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
  .setManifestKeyPrefix('dist/');

// CSS CONFIG
Encore.enablePostCssLoader();

// IMAGES CONFIG
Encore.copyFiles({
  from: './assets/images',
  to: 'images/[path][name].[ext]',
  pattern: /\.(png|jpg|jpeg|gif|svg|webp)$/
}).copyFiles({
  from: './assets/favicons',
  to: 'favicons/[path][name].[ext]',
  pattern: /\.(png|jpg|jpeg|gif|ico|svg|webp|webmanifest)$/
});

Encore.configureImageRule({ type: 'javascript/auto' }, (loaderRule) => {
  loaderRule.test = /\.(png|jpg|jpeg|gif|ico|webp)$/;
  loaderRule.oneOf = [
    { resourceQuery: /copy-files-loader/, type: 'javascript/auto' },
    {
      type: 'asset/resource',
      generator: { filename: 'images/[name].[hash:8][ext]' },
      parser: {}
    }
  ];
});

Encore.addRule({
  test: /\.svg$/,
  type: 'javascript/auto',
  use: [
    {
      loader: '@svgr/webpack',
      options: {
        svgoConfig: {
          plugins: {
            removeViewBox: false
          }
        }
      }
    },
    'file-loader'
  ]
});

Encore.addPlugin(
  new SVGSpritemapPlugin('assets/svg-icons/**/*.svg', {
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

Encore.configureManifestPlugin((options) => {
  options.removeKeyHash = /(?<=dist\/sprite\.)(\w*\.)(?=svg)/;
});

// SERVER CONFIG
Encore.configureDevServerOptions((options) => {
  options.headers = {
    'Access-Control-Allow-Origin': '*'
  };
  options.client.overlay = true;
  options.firewall = false;

  options.onBeforeSetupMiddleware = (server) => {
    chokidar
      .watch(['./**/*.html', './**/*.tpl'], {
        alwaysStat: true,
        atomic: false,
        followSymlinks: false,
        ignoreInitial: true,
        ignorePermissionErrors: true,
        persistent: true,
        usePolling: true
      })
      .on('all', function () {
        server.sockWrite(server.sockets, 'content-changed');
      });
  };
});

module.exports = Encore.getWebpackConfig();
