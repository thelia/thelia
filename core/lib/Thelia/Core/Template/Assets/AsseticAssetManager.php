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

use Assetic\AssetManager;
use Assetic\FilterManager;
use Assetic\Filter;
use Assetic\Factory\AssetFactory;
use Assetic\AssetWriter;
use Thelia\Model\ConfigQuery;
use Thelia\Log\Tlog;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This class is a simple helper for generating assets using Assetic.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AsseticAssetManager implements AssetManagerInterface
{
    protected $debugMode;

    protected $source_file_extensions = array('less', 'js', 'coffee', 'html', 'tpl', 'htm', 'xml');

    protected $assetFilters = [];

    public function __construct($debugMode)
    {
        $this->debugMode = $debugMode;
    }

    /**
     * Create a stamp form the modification time of the content of the given directory and all of its subdirectories
     *
     * @param  string $directory ther directory name
     * @return string the stamp of this directory
     */
    protected function getStamp($directory)
    {
        $stamp = '';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $stamp .= $file->getMTime();
        }

        return md5($stamp);
    }

    /**
     * Check if a file is a source asset file
     *
     * @param \SplFileInfo $fileInfo
     *
     * @return bool
     */
    protected function isSourceFile(\SplFileInfo $fileInfo)
    {
        return in_array($fileInfo->getExtension(), $this->source_file_extensions);
    }

    /**
     * Recursively copy assets from the source directory to the destination
     * directory in the web space, omitting source files.
     *
     * @param  Filesystem        $fs
     * @param  string            $from_directory the source
     * @param  string            $to_directory   the destination
     * @throws \RuntimeException if a problem occurs.
     */
    protected function copyAssets(Filesystem $fs, $from_directory, $to_directory)
    {
        Tlog::getInstance()->addDebug("Copying assets from $from_directory to $to_directory");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from_directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $fs->mkdir($to_directory, 0777);

        /** @var \RecursiveDirectoryIterator $iterator */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dest_dir = $to_directory . DS . $iterator->getSubPathName();

                if (! is_dir($dest_dir)) {
                    if ($fs->exists($dest_dir)) {
                        $fs->remove($dest_dir);
                    }

                    $fs->mkdir($dest_dir, 0777);
                }
            } elseif (! $this->isSourceFile($item)) {
                // We don't copy source files

                $dest_file = $to_directory . DS . $iterator->getSubPathName();

                if ($fs->exists($dest_file)) {
                    $fs->remove($dest_file);
                }

                $fs->copy($item, $dest_file);
            }
        }
    }

    /**
     * Compute the destination directory path, from the source directory and the
     * base directory of the web assets
     *
     * @param string $webAssetsDirectoryBase Base base directory of the web assets
     * @param string $webAssetsTemplate      The template directory, relative to '<thelia_root>/templates'
     * @param string $webAssetsKey           the assets key : module name or 0 for template assets
     *
     * @internal param string $source_assets_directory the source directory
     * @return string the full path of the destination directory
     */
    protected function getDestinationDirectory($webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey)
    {
        // Compute the absolute path of the output directory
        return $webAssetsDirectoryBase . DS . $webAssetsTemplate . DS . $webAssetsKey;
    }

    /**
     * Prepare an asset directory by checking that no changes occured in
     * the source directory. If any change is detected, the whole asset directory
     * is copied in the web space.
     *
     * @param string $sourceAssetsDirectory  the full path to the source assets directory
     * @param string $webAssetsDirectoryBase the base directory of the web based asset directory
     * @param        $webAssetsTemplate
     * @param string $webAssetsKey           the assets key : module name or 0 for base template
     *
     * @throws \RuntimeException if something goes wrong.
     */
    public function prepareAssets($sourceAssetsDirectory, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey)
    {
        // Compute the absolute path of the output directory
        $to_directory = $this->getDestinationDirectory($webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey);

        // Get a path to the stamp file
        $stamp_file_path = $to_directory . DS . '.source-stamp';

        // Get the last stamp of source assets directory
        $prev_stamp = @file_get_contents($stamp_file_path);

        // Get the current stamp of the source directory
        $curr_stamp = $this->getStamp($sourceAssetsDirectory);

        if ($prev_stamp !== $curr_stamp) {
            $fs = new Filesystem();

            $tmp_dir = "$to_directory.tmp";

            $fs->remove($tmp_dir);

            // Copy the whole source dir in a temp directory
            $this->copyAssets($fs, $sourceAssetsDirectory, $tmp_dir);

            // Remove existing directory
            if ($fs->exists($to_directory)) {
                $fs->remove($to_directory);
            }

            // Put in place the new directory
            $fs->rename($tmp_dir, $to_directory);

            if (false === @file_put_contents($stamp_file_path, $curr_stamp)) {
                throw new \RuntimeException(
                    "Failed to create asset stamp file $stamp_file_path. Please check that your web server has the proper access rights to do that."
                );
            }
        }
    }

    /**
     * Decode the filters names, and initialize the Assetic FilterManager
     *
     * @param  FilterManager             $filterManager the Assetic filter manager
     * @param  string                    $filters       a comma separated list of filter names
     * @throws \InvalidArgumentException if a wrong filter is passed
     * @return array                     an array of filter names
     */
    protected function decodeAsseticFilters(FilterManager $filterManager, $filters)
    {
        if (!empty($filters)) {
            $filter_list = explode(',', $filters);

            foreach ($filter_list as $filter_name) {
                $filter_name = trim($filter_name);

                foreach ($this->assetFilters as $filterIdentifier => $filterInstance) {
                    if ($filterIdentifier == $filter_name) {
                        $filterManager->set($filterIdentifier, $filterInstance);

                        // No, goto is not evil.
                        goto filterFound;
                    }
                }

                throw new \InvalidArgumentException("Unsupported Assetic filter: '$filter_name'");
                break;

                filterFound:
            }
        } else {
            $filter_list = array();
        }

        return $filter_list;
    }

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
     * @return string The URL to the generated asset file.
     */
    public function processAsset($assetSource, $assetDirectoryBase, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey, $outputUrl, $assetType, $filters, $debug)
    {
        Tlog::getInstance()->addDebug(
            "Processing asset: assetSource=$assetSource, assetDirectoryBase=$assetDirectoryBase, webAssetsDirectoryBase=$webAssetsDirectoryBase, webAssetsTemplate=$webAssetsTemplate, webAssetsKey=$webAssetsKey, outputUrl=$outputUrl"
        );

        $assetName = basename($assetSource);
        $inputDirectory = realpath(dirname($assetSource));

        $assetFileDirectoryInAssetDirectory = trim(str_replace(array($assetDirectoryBase, $assetName), '', $assetSource), DS);

        $am = new AssetManager();
        $fm = new FilterManager();

        // Get the filter list
        $filterList = $this->decodeAsseticFilters($fm, $filters);

        // Factory setup
        $factory = new AssetFactory($inputDirectory);

        $factory->setAssetManager($am);
        $factory->setFilterManager($fm);

        $factory->setDefaultOutput('*' . (!empty($assetType) ? '.' : '') . $assetType);

        $factory->setDebug($debug);

        $asset = $factory->createAsset($assetName, $filterList);

        $outputDirectory = $this->getDestinationDirectory($webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey);

        // Get the URL part from the relative path
        $outputRelativeWebPath = $webAssetsTemplate . DS . $webAssetsKey;

        $assetTargetFilename = $asset->getTargetPath();

        /*
         * This is the final name of the generated asset
         * We preserve file structure intending to keep - for example - relative css links working
         */
        $assetDestinationPath = $outputDirectory . DS . $assetFileDirectoryInAssetDirectory . DS . $assetTargetFilename;

        Tlog::getInstance()->addDebug("Asset destination full path: $assetDestinationPath");

        // We generate an asset only if it does not exists, or if the asset processing is forced in development mode
        if (! file_exists($assetDestinationPath) || ($this->debugMode && ConfigQuery::read('process_assets', true))) {
            $writer = new AssetWriter($outputDirectory . DS . $assetFileDirectoryInAssetDirectory);

            Tlog::getInstance()->addDebug("Writing asset to $outputDirectory" . DS . "$assetFileDirectoryInAssetDirectory");

            $writer->writeAsset($asset);
        }

        // Normalize path to generate a valid URL
        if (DS != '/') {
            $outputRelativeWebPath = str_replace(DS, '/', $outputRelativeWebPath);
            $assetFileDirectoryInAssetDirectory = str_replace(DS, '/', $assetFileDirectoryInAssetDirectory);
            $assetTargetFilename = str_replace(DS, '/', $assetTargetFilename);
        }

        return rtrim($outputUrl, '/') . '/' . trim($outputRelativeWebPath, '/') . '/' . trim($assetFileDirectoryInAssetDirectory, '/') . '/' . ltrim($assetTargetFilename, '/');
    }

    /**
     * @inheritdoc
     */
    public function isDebugMode()
    {
        return $this->debugMode;
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFilter($filterIdentifier, $filter)
    {
        $this->assetFilters[$filterIdentifier] = $filter;
    }
}
