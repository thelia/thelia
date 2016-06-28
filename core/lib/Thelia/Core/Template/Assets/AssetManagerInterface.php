<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Assets;

use Assetic\Filter\FilterInterface;

interface AssetManagerInterface
{
    /**
     * Prepare an asset directory by checking that no changes occured in
     * the source directory. If any change is detected, the whole asset directory
     * is copied in the web space.
     *
     * @param string $sourceAssetsDirectory  the full path to the source asstes directory
     * @param string $webAssetsDirectoryBase the base directory of the web based asset directory
     * @param        $webAssetsTemplate
     * @param string $webAssetsKey           the assets key : module name or 0 for base template
     *
     * @throws \RuntimeException if something goes wrong.
     *
     * @internal param string $source_assets_directory the full path to the source asstes directory
     * @internal param string $web_assets_directory_base the base directory of the web based asset directory
     * @internal param string $key the assets key : module name or 0 for base template
     */
    public function prepareAssets($sourceAssetsDirectory, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey);

    /**
     * Generates assets from $asset_path in $output_path, using $filters.
     *
     * @param        $assetSource
     * @param        $assetDirectoryBase
     * @param string $webAssetsDirectoryBase the full path to the asset file (or file collection, e.g. *.less)
     *
     * @param string $webAssetsTemplate the full disk path to the base assets output directory in the web space
     * @param        $webAssetsKey
     * @param string $outputUrl         the URL to the base assets output directory in the web space
     *
     * @param string $assetType the asset type: css, js, ... The generated files will have this extension. Pass an empty string to use the asset source extension.
     * @param array  $filters   a list of filters, as defined below (see switch($filter_name) ...)
     *
     * @param boolean $debug true / false
     *
     * @internal param string $web_assets_directory_base the full disk path to the base assets output directory in the web space
     * @internal param string $output_url the URL to the base assets output directory in the web space
     *
     * @return string The URL to the generated asset file.
     */
    public function processAsset($assetSource, $assetDirectoryBase, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey, $outputUrl, $assetType, $filters, $debug);

    /**
     * @return bool true if the AssetManager was started in debug mode
     */
    public function isDebugMode();

    /**
     * Register an asset filter
     * @param string $filterIdentifier
     * @param FilterInterface $filter
     */
    public function registerAssetFilter($filterIdentifier, $filter);
}
