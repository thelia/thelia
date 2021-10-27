require('dotenv').config();

const chokidar = require('chokidar');

const Encore = require('@symfony/webpack-encore');
const ESLintPlugin = require('eslint-webpack-plugin');
const path = require('path');
const webpack = require('webpack');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore.configureFriendlyErrorsPlugin((options) => {
  options.clearConsole = true;
});

Encore.configureBabelPresetEnv((config) => {
  config.useBuiltIns = 'usage';
  config.corejs = 3;
});

Encore.addPlugin(new ESLintPlugin());

// ENTRIES
Encore.addEntry('app', './assets/js/app.js');

console.log(__dirname, path.basename(__dirname));

Encore.setOutputPath('dist/')
  .setPublicPath(
    process.env.NODE_ENV === 'production'
      ? '/assets/backOffice/' + path.basename(__dirname)
      : '/dist'
  )
  .splitEntryChunks()
  .enableReactPreset()
  .enableSingleRuntimeChunk()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .setManifestKeyPrefix('')
  .addPlugin(
    new webpack.ProvidePlugin({
      TheliaJS: 'TheliaJS'
    })
  );

Encore.cleanupOutputBeforeBuild();

// CSS CONFIG
Encore.enablePostCssLoader();

Encore.configureManifestPlugin((options) => {
  options.removeKeyHash = /(?<=sprite\.)(\w*\.)(?=svg)/;
});

Encore.copyFiles({
  from: './assets/images',
  to: 'images/[path][name].[ext]',
  pattern: /\.(png|jpg|jpeg|gif|svg|webp)$/
}).copyFiles({
  from: './assets/favicons',
  to: 'favicons/[path][name].[ext]',
  pattern: /\.(png|jpg|jpeg|gif|ico|svg|webp|webmanifest)$/
});

// SERVER CONFIG
Encore.configureDevServerOptions((options) => {
  options.headers = {
    'Access-Control-Allow-Origin': '*'
  };
  options.allowedHosts = 'all';

  options.onBeforeSetupMiddleware = (server) => {
    if (!server) {
      throw new Error('webpack-dev-server is not defined');
    }

    chokidar
      .watch(['./**/*.html', './**/*.tpl'], {
        alwaysStat: true,
        atomic: false,
        followSymlinks: false,
        ignoreInitial: true,
        ignorePermissionErrors: true,
        persistent: true
      })
      .on('all', function (e) {
        server.sendMessage(server.webSocketServer.clients, 'content-changed');
      });
  };
});

module.exports = Encore.getWebpackConfig();
