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
use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Filter;
use Assetic\Factory\AssetFactory;
use Assetic\Factory\Worker\CacheBustingWorker;
use Assetic\AssetWriter;
use Thelia\Model\ConfigQuery;

/**
 * This class is a simple helper for generating assets using Assetic.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AsseticHelper
{
    /**
     * Generates assets from $asset_path in $output_path, using $filters.
     *
     * @param  string                    $asset_path  the full path to the asset file (or file collection)
     * @param  string                    $output_path the full disk path to the output directory (shoud be visible to web server)
     * @param  string                    $output_url  the URL to the generated asset directory
     * @param  string                    $asset_type  the asset type: css, js, ... The generated files will have this extension. Pass an empty string to use the asset source extension.
     * @param  array                     $filters     a list of filters, as defined below (see switch($filter_name) ...)
     * @param  boolean                   $debug       true / false
     * @param  boolean                   $dev_mode    true / false. If true, assets are not cached and always compiled.
     * @throws \InvalidArgumentException if an invalid filter name is found
     * @return string                    The URL to the generated asset file.
     */
    public function asseticize($asset_path, $output_path, $output_url, $asset_type, $filters, $debug, $dev_mode = false)
    {
        $asset_name = basename($asset_path);
        $asset_dir = dirname($asset_path);

        $am = new AssetManager();
        $fm = new FilterManager();

        if (!empty($filters)) {
            $filter_list = explode(',', $filters);

            foreach ($filter_list as $filter_name) {

                $filter_name = trim($filter_name);

                switch ($filter_name) {
                case 'less':
                    $fm->set('less', new Filter\LessphpFilter());
                    break;

                case 'sass':
                    $fm->set('sass', new Filter\Sass\SassFilter());
                    break;

                case 'cssembed':
                    $fm->set('cssembed', new Filter\PhpCssEmbedFilter());
                    break;

                case 'cssrewrite':
                    $fm->set('cssrewrite', new Filter\CssRewriteFilter());
                    break;

                case 'cssimport':
                    $fm->set('cssimport', new Filter\CssImportFilter());
                    break;

                case 'compass':
                    $fm->set('compass', new Filter\CompassFilter());
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported Assetic filter: '$filter_name'");
                    break;
                }
            }
        }
        else {
            $filter_list = array();
        }

        // Factory setup
        $factory = new AssetFactory($asset_dir);

        $factory->setAssetManager($am);
        $factory->setFilterManager($fm);

        $factory->setDefaultOutput('*' . (!empty($asset_type) ? '.' : '') . $asset_type);

        $factory->setDebug($debug);

        $factory->addWorker(new CacheBustingWorker('-'));

        // We do not pass the filter list here, juste to get the asset file name
        $asset = $factory->createAsset($asset_name);

        $asset_target_path = $asset->getTargetPath();

        $target_file = sprintf("%s/%s", $output_path, $asset_target_path);

        // As it seems that assetic cannot handle a real file cache, let's do the job ourselves.
        // It works only if the CacheBustingWorker is used, as a new file name is generated for each version.
        //
        // the previous version of the file is deleted, by getting the first part of the ouput file name
        // (the one before '-'), and delete aby file beginning with the same string. Example:
        //     old name: 3bc974a-dfacc1f.css
        //     new name: 3bc974a-ad3ef47.css
        //
        //     before generating 3bc974a-ad3ef47.css, delete 3bc974a-* files.
        //
        if ($dev_mode == true || !file_exists($target_file)) {

            if (ConfigQuery::read('process_assets', true)) {

                // Delete previous version of the file
                list($commonPart, $dummy) = explode('-', $asset_target_path);

                foreach (glob("$output_path/$commonPart-*") as $filename) {
                    @unlink($filename);
                }

                // Apply filters now
                foreach ($filter_list as $filter) {
                    if ('?' != $filter[0]) {
                        $asset->ensureFilter($fm->get($filter));
                    }
                    elseif (!$debug) {
                        $asset->ensureFilter($fm->get(substr($filter, 1)));
                    }
                }

                $writer = new AssetWriter($output_path);

                $writer->writeAsset($asset);
            }
        }

        return rtrim($output_url, '/') . '/' . $asset_target_path;
    }

    // Create a hash of the current assets directory
    public function getStamp($directory)
    {

        $stamp = '';

        foreach (new \DirectoryIterator($directory) as $fileInfo) {

            if ($fileInfo->isDot()) continue;

            if ($fileInfo->isDir()) {
                $stamp .= $this->getStamp($fileInfo->getPathName());
            }

            if ($fileInfo->isFile()) {
                $stamp .= $fileInfo->getMTime();
            }
        }

        return $stamp;
    }

    public function copyAssets($from_directory, $to_directory)
    {

        echo "copy $from_directory to $to_directory\n";

        $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($from_directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dest_dir = $to_directory . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

                if (!is_dir($dest_dir)) {
                    if (file_exists($dest_dir)) {
                        @unlink($dest_dir);
                    }

                    if (!mkdir($dest_dir, 0777, true)) {
                        throw new \RuntimeException(
                                "Failed to create directory  $dest_dir. Please check that your web server has the proper access rights");
                    }
                }
            }
            else {
                $dest_file = $to_directory . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

                if (file_exists($dest_file)) {
                    @unlink($dest_file);
                }

                if (!copy($item, $dest_file)) {
                    throw new \RuntimeException(
                            "Failed to copy  $source_file to $dest_file. Please check that your web server has the proper access rights");
                }
            }
        }
    }
}