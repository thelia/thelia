<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Template\Assets;

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
     */
    public function prepareAssets($sourceAssetsDirectory, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey);

    /**
     * Generates assets from $asset_path in $output_path, using $filters.
     *
     * @param          $assetSource
     * @param          $assetDirectoryBase
     * @param  string  $webAssetsDirectoryBase the full path to the asset file (or file collection, e.g. *.less)
     *
     * @param  string  $webAssetsTemplate      the full disk path to the base assets output directory in the web space
     * @param          $webAssetsKey
     * @param  string  $outputUrl              the URL to the base assets output directory in the web space
     *
     * @param  string  $assetType              the asset type: css, js, ... The generated files will have this extension. Pass an empty string to use the asset source extension.
     * @param  array   $filters                a list of filters, as defined below (see switch($filter_name) ...)
     *
     * @param  boolean $debug                  true / false
     *
     * @return string                          The URL to the generated asset file.
     */
     public function processAsset($assetSource, $assetDirectoryBase, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey, $outputUrl, $assetType, $filters, $debug);
}