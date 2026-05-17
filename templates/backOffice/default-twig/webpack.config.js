const Encore = require('@symfony/webpack-encore');
const path = require('path');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('./dist/')
    .setPublicPath('/templates-assets/backOffice/' + path.basename(__dirname) + '/dist')
    .setManifestKeyPrefix('dist/')

    .addEntry('app', './assets/app.js')

    .splitEntryChunks()
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    .copyFiles({
        from: './assets/img',
        to: 'img/[path][name].[ext]',
        pattern: /\.(png|jpe?g|svg|webp)$/,
    })

    .enableSassLoader()

    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })

    .enableStimulusBridge('./assets/controllers.json');

module.exports = Encore.getWebpackConfig();
