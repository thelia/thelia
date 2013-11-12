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
use Assetic\AssetWriter;
use Thelia\Model\ConfigQuery;
use Thelia\Log\Tlog;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * This class is a simple helper for generating assets using Assetic.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AsseticHelper
{
    protected $source_file_extensions = array('less', 'js', 'coffee', 'html', 'tpl', 'htm', 'xml');

    /**
     * Create a stamp form the modification time of the content of the given directory and all of its subdirectories
     *
     * @param string $directory ther directory name
     * @return string the stamp of this directory
     */
    protected function getStamp($directory)
    {
        $stamp = '';

        $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($iterator as $file) {
            $stamp .= $file->getMTime();
        }

        return md5($stamp);
    }

    /**
     * Check if a file is a source asset file
     *
     * @param \DirectoryIterator $fileInfo
     */
    protected function isSourceFile(\SplFileInfo $fileInfo) {
        return in_array($fileInfo->getExtension(), $this->source_file_extensions);
    }

    /**
     * Recursively copy assets from the source directory to the destination
     * directory in the web space, ommiting source files.
     *
     * @param string $from_directory the source
     * @param string $to_directory the destination
     * @throws \RuntimeException if a problem occurs.
     */
    protected function copyAssets(Filesystem $fs, $from_directory, $to_directory)
    {
        Tlog::getInstance()->addDebug("Copying assets from ", $from_directory, " to ", $to_directory);

        $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($from_directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dest_dir = $to_directory . DS . $iterator->getSubPathName();

                if (! is_dir($dest_dir)) {
                    if ($fs->exists($dest_dir)) {
                        $fs->remove($dest_dir);
                    }

                    $fs->mkdir($dest_dir, 0777);
                }
            }
            // We don't copy source files
            else if (! $this->isSourceFile($item)) {

                $dest_file = $to_directory . DS . $iterator->getSubPathName();

                if ($fs->exists($dest_file)) {
                    $fs->remove($dest_file);
                }

                $fs->copy($item, $dest_file);
            }
        }
    }

    /**
     * Compite the assets path relative to the base template directory
     *
     * @param string $source_assets_directory the source directory
     * @param string $web_assets_directory_base base directory of the web assets
     * @return the full path of the destination directory
     */
    protected function getRelativeDirectoryPath($source_assets_directory, $web_assets_directory_base)
    {
        $source_assets_directory = realpath($source_assets_directory);

        // Remove base path from asset source path to get a path relative to the template base
        // and use it to create the destination path.
        return str_replace(
                realpath(THELIA_ROOT),
                '',
                $source_assets_directory
        );
    }

    /**
     * Compute the destination directory path, from the source directory and the
     * base directory of the web assets
     *
     * @param string $source_assets_directory the source directory
     * @param string $web_assets_directory_base base directory of the web assets
     * @return the full path of the destination directory
     */
    protected function getDestinationDirectory($source_assets_directory, $web_assets_directory_base)
    {
        // Compute the absolute path of the output directory
        return $web_assets_directory_base . $this->getRelativeDirectoryPath($source_assets_directory, $web_assets_directory_base);
    }

    /**
     * Prepare an asset directory by checking that no changes occured in
     * the source directory. If any change is detected, the whole asset directory
     * is copied in the web space.
     *
     * @param string $source_assets_directory the full path to the source asstes directory
     * @param string $web_assets_directory_base the base directory of the web based asset directory
     * @throws \RuntimeException if something goes wrong.
     */
    public function prepareAssets($source_assets_directory, $web_assets_directory_base) {

        // Compute the absolute path of the output directory
        $to_directory = $this->getDestinationDirectory($source_assets_directory, $web_assets_directory_base);

        // Get a path to the stamp file
        $stamp_file_path = $to_directory . DS . '.source-stamp';

        // Get the last stamp of source assets directory
        $prev_stamp = @file_get_contents($stamp_file_path);

        // Get the current stamp of the source directory
        $curr_stamp = $this->getStamp($source_assets_directory);

        if ($prev_stamp !== $curr_stamp) {

            $fs = new Filesystem();

            //FIXME: lock the stuff ?
/*
            $lock_file = "$web_assets_directory_base/assets-".md5($source_assets_directory)."-generation-lock.txt";

            if (! $fp = fopen($lock_file, "w")) {
                throw new IOException(sprintf('Failed to open lock file %s', $lock_file));
            }

            if (flock($fp, LOCK_EX|LOCK_NB)) { // do an exclusive lock
*/
                $tmp_dir = "$to_directory.tmp";

                $fs->remove($tmp_dir);

                // Copy the whole source dir in a temp directory
                $this->copyAssets($fs, $source_assets_directory, $tmp_dir);

                // Remove existing directory
                if ($fs->exists($to_directory)) $fs->remove($to_directory);

                // Put in place the new directory
                $fs->rename($tmp_dir, $to_directory);
/*
                // Release the lock
                flock($fp, LOCK_UN);

                // Remove the lock file
                @fclose($fp);

                $fs->remove($lock_file);
*/
                if (false === @file_put_contents($stamp_file_path, $curr_stamp)) {
                    throw new \RuntimeException(
                            "Failed to create asset stamp file $stamp_file_path. Please check that your web server has the proper access rights to do that.");
                }
/*            }
            else {
                @fclose($fp);
            }
*/
        }
    }

    /**
     * Decode the filters names, and initialize the Assetic FilterManager
     *
     * @param FilterManager $filterManager the Assetic filter manager
     * @param string $filters a comma separated list of filter names
     * @throws \InvalidArgumentException if a wrong filter is passed
     * @return an array of filter names
     */
    protected function decodeAsseticFilters(FilterManager $filterManager, $filters) {

        if (!empty($filters)) {

            $filter_list = explode(',', $filters);

            foreach ($filter_list as $filter_name) {

                $filter_name = trim($filter_name);

                switch ($filter_name) {
                    case 'less':
                        $filterManager->set('less', new Filter\LessphpFilter());
                        break;

                    case 'sass':
                        $filterManager->set('sass', new Filter\Sass\SassFilter());
                        break;

                    case 'cssembed':
                        $filterManager->set('cssembed', new Filter\PhpCssEmbedFilter());
                        break;

                    case 'cssrewrite':
                        $filterManager->set('cssrewrite', new Filter\CssRewriteFilter());
                        break;

                    case 'cssimport':
                        $filterManager->set('cssimport', new Filter\CssImportFilter());
                        break;

                    case 'compass':
                        $filterManager->set('compass', new Filter\CompassFilter());
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

        return $filter_list;
    }

    /**
     * Generates assets from $asset_path in $output_path, using $filters.
     *
     * @param  string                    $asset_path  the full path to the asset file (or file collection, e.g. *.less)
     *
     * @param  string                    $web_assets_directory_base the full disk path to the base assets output directory in the web space
     * @param  string                    $output_url  the URL to the base assets output directory in the web space
     *
     * @param  string                    $asset_type  the asset type: css, js, ... The generated files will have this extension. Pass an empty string to use the asset source extension.
     * @param  array                     $filters     a list of filters, as defined below (see switch($filter_name) ...)
     *
     * @param  boolean                   $debug       true / false
     * @param  boolean                   $dev_mode    true / false. If true, assets are not cached and always compiled.
     * @throws \InvalidArgumentException if an invalid filter name is found
     * @return string                    The URL to the generated asset file.
     */
    public function asseticize($asset_path, $web_assets_directory_base, $output_url, $asset_type, $filters, $debug, $dev_mode = false)
    {
        $asset_name = basename($asset_path);
        $input_directory = realpath(dirname($asset_path));

        $am = new AssetManager();
        $fm = new FilterManager();

        // Get the filter list
        $filter_list = $this->decodeAsseticFilters($fm, $filters);

        // Factory setup
        $factory = new AssetFactory($input_directory);

        $factory->setAssetManager($am);
        $factory->setFilterManager($fm);

        $factory->setDefaultOutput('*' . (!empty($asset_type) ? '.' : '') . $asset_type);

        $factory->setDebug($debug);

        $asset = $factory->createAsset($asset_name, $filter_list);

        $input_directory = realpath(dirname($asset_path));

        $output_directory = $this->getDestinationDirectory($input_directory, $web_assets_directory_base);

        // Get the URL part from the relative path
        $output_relative_path = $this->getRelativeDirectoryPath($input_directory, $web_assets_directory_base);

        $output_relative_web_path = rtrim(str_replace('\\', '/', $output_relative_path), '/') . '/';

        $asset_target_filename = $asset->getTargetPath();

        // This is the final name of the generated asset
        $asset_destination_path = $output_directory . DS . $asset_target_filename;

        Tlog::getInstance()->addDebug("Asset destination name: ", $asset_destination_path);

        // We generate an asset only if it does not exists, or if the asset processing is forced in development mode
        if (! file_exists($asset_destination_path) || ($dev_mode && ConfigQuery::read('process_assets', true)) ) {

            $writer = new AssetWriter($output_directory);

            Tlog::getInstance()->addDebug("Writing asset to $output_directory");

            $writer->writeAsset($asset);
        }

        return rtrim($output_url, '/') . '/' . $output_relative_web_path . $asset_target_filename;
    }
}