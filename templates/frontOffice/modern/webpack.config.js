require('dotenv').config();
const path = require('path');
const SVGSpritemapPlugin = require('svg-spritemap-webpack-plugin');
const Encore = require('@symfony/webpack-encore');
const ESLintPlugin = require('eslint-webpack-plugin');

const chokidar = require('chokidar');

const svgoConfig = {
  plugins: [
    'removeDoctype',
    'removeXMLProcInst',
    'removeComments',
    'removeMetadata',
    'removeEditorsNSData',
    'cleanupAttrs',
    'mergeStyles',
    'inlineStyles',
    'minifyStyles',
    'removeUselessDefs',
    'cleanupNumericValues',
    'convertColors',
    'removeUnknownsAndDefaults',
    'removeNonInheritableGroupAttrs',
    'removeUselessStrokeAndFill',
    'removeViewBox',
    'cleanupEnableBackground',
    'removeHiddenElems',
    'removeEmptyText',
    'convertShapeToPath',
    'convertEllipseToCircle',
    'moveElemsAttrsToGroup',
    'moveGroupAttrsToElems',
    'collapseGroups',
    'convertPathData',
    'convertTransform',
    'removeEmptyAttrs',
    'removeEmptyContainers',
    'mergePaths',
    'removeUnusedNS',
    'sortDefsChildren',
    'removeTitle',
    'removeDesc',
    {
      name: 'cleanupIDs',
      params: {
        prefix: {
          toString() {
            return `${Math.random().toString(36).substr(2, 9)}`;
          }
        }
      }
    },
    {
      name: 'removeAttrs',
      params: {
        attrs: '(data-name)'
      }
    }
  ]
};

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

// GENERAL
Encore.configureFriendlyErrorsPlugin((options) => {
  options.clearConsole = true;
});
Encore.configureBabel((config) => {
  config.plugins.push('@babel/plugin-transform-runtime');
});

Encore.addPlugin(new ESLintPlugin());

// ENTRIES
Encore.addEntry('app', './assets/js/app')
  .addEntry('home', './assets/js/routes/home')
  .addEntry('category', './assets/js/routes/category')
  .addEntry('product', './assets/js/routes/product')
  .addEntry('register', './assets/js/routes/register')
  .addEntry('address', './assets/js/routes/address')
  .addEntry('search', './assets/js/routes/search')
  .addEntry('delivery', './assets/js/routes/delivery');

Encore.setOutputPath('dist/')
  .setPublicPath(
    process.env.NODE_ENV === 'production'
      ? '/templates-assets/frontOffice/' + path.basename(__dirname) + '/dist'
      : '/dist'
  )
  .addAliases({
    '@components': path.resolve(__dirname, './components'),
    '@js': path.resolve(__dirname, './assets/js'),
    '@redux': path.resolve(__dirname, './assets/js/redux'),
    '@utils': path.resolve(__dirname, './assets/js/utils'),
    '@standalone': path.resolve(__dirname, './assets/js/standalone'),
    '@css': path.resolve(__dirname, './assets/css'),
    '@images': path.resolve(__dirname, './assets/images'),
    '@icons': path.resolve(__dirname, './assets/svg-icons'),
    '@fonts': path.resolve(__dirname, './assets/fonts'),
    '@favicons': path.resolve(__dirname, './assets/favicons')
  })
  .splitEntryChunks()
  .enableTypeScriptLoader()
  .enableReactPreset()
  .enableSingleRuntimeChunk()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .addStyleEntry('print', './assets/css/print.css')
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
        svgoConfig: svgoConfig
      }
    },
    'file-loader'
  ]
});

Encore.addPlugin(
  new SVGSpritemapPlugin('assets/svg-icons/**/*.svg', {
    sprite: {
      prefix: 'svg-icons-'
    },
    output: {
      filename: 'sprite.[contenthash].svg',
      chunk: {
        name: 'sprite',
        keep: true
      },
      svg4everybody: false,
      svgo: svgoConfig
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
  options.allowedHosts = 'all';
  options.hot = false;
  options.liveReload = true;
  options.client = {
    overlay: true
  };

  options.onListening = (devServer) => {
    chokidar
      .watch(['./**/*.html', './**/*.tpl'], {
        alwaysStat: true,
        atomic: false,
        followSymlinks: false,
        ignoreInitial: true,
        ignorePermissionErrors: true,
        persistent: true
      })
      .on('all', function () {
        devServer.sendMessage(
          devServer.webSocketServer.clients,
          'content-changed'
        );
      });
  };
});

Encore.cleanupOutputBeforeBuild();

module.exports = Encore.getWebpackConfig();
