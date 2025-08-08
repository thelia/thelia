<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Assets;

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Log\Tlog;
use Thelia\Model\ConfigQuery;

/**
 * This class is a simple helper for generating assets using Assetic.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class AssetManager implements AssetManagerInterface
{
    protected $debugMode;

    protected $source_file_extensions = ['less', 'js', 'coffee', 'html', 'tpl', 'htm', 'xml'];

    protected $assetFilters = [];

    public function __construct($kernelDebug)
    {
        $this->debugMode = $kernelDebug;
    }

    /**
     * Create a stamp form the modification time of the content of the given directory and all of its subdirectories.
     *
     * @param string $directory ther directory name
     *
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
     * Check if a file is a source asset file.
     *
     * @return bool
     */
    protected function isSourceFile(\SplFileInfo $fileInfo)
    {
        return \in_array($fileInfo->getExtension(), $this->source_file_extensions);
    }

    /**
     * Recursively copy assets from the source directory to the destination
     * directory in the web space, omitting source files.
     *
     * @param string $from_directory the source
     * @param string $to_directory   the destination
     *
     * @throws \RuntimeException if a problem occurs
     */
    protected function copyAssets(Filesystem $fs, $from_directory, $to_directory): void
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
                $dest_dir = $to_directory.DS.$iterator->getSubPathName();

                if (!is_dir($dest_dir)) {
                    if ($fs->exists($dest_dir)) {
                        $fs->remove($dest_dir);
                    }

                    $fs->mkdir($dest_dir, 0777);
                }
            } elseif (!$this->isSourceFile($item)) {
                // We don't copy source files

                $dest_file = $to_directory.DS.$iterator->getSubPathName();

                if ($fs->exists($dest_file)) {
                    $fs->remove($dest_file);
                }

                $fs->copy($item, $dest_file);
            }
        }
    }

    /**
     * Compute the destination directory path, from the source directory and the
     * base directory of the web assets.
     *
     * @param string $webAssetsDirectoryBase Base base directory of the web assets
     * @param string $webAssetsTemplate      The template directory, relative to '<thelia_root>/templates'
     * @param string $webAssetsKey           the assets key : module name or 0 for template assets
     *
     * @internal param string $source_assets_directory the source directory
     *
     * @return string the full path of the destination directory
     */
    protected function getDestinationDirectory($webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey): string
    {
        // Compute the absolute path of the output directory
        return $webAssetsDirectoryBase.DS.$webAssetsTemplate.DS.$webAssetsKey;
    }

    /**
     * Prepare an asset directory by checking that no changes occured in
     * the source directory. If any change is detected, the whole asset directory
     * is copied in the web space.
     *
     * @param string $sourceAssetsDirectory  the full path to the source assets directory
     * @param string $webAssetsDirectoryBase the base directory of the web based asset directory
     * @param string $webAssetsKey           the assets key : module name or 0 for base template
     *
     * @throws \RuntimeException if something goes wrong
     */
    public function prepareAssets($sourceAssetsDirectory, $webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey): void
    {
        // Compute the absolute path of the output directory
        $to_directory = $this->getDestinationDirectory($webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey);

        // Get a path to the stamp file
        $stamp_file_path = $to_directory.DS.'.source-stamp';

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
     * Generates assets from $asset_path in $output_path, using $filters.
     *
     * @throws \Exception
     */
    public function processAsset(
        string $assetSource,
        string $assetDirectoryBase,
        string $webAssetsDirectoryBase,
        string $webAssetsTemplate,
        string $webAssetsKey,
        string $outputUrl,
        string $assetType,
        array|string $filters,
        bool $debug
    ): string {
        Tlog::getInstance()->addDebug(
            "Processing asset: assetSource=$assetSource, assetDirectoryBase=$assetDirectoryBase, webAssetsDirectoryBase=$webAssetsDirectoryBase, webAssetsTemplate=$webAssetsTemplate, webAssetsKey=$webAssetsKey, outputUrl=$outputUrl"
        );

        $assetName = basename($assetSource);
        $assetFileDirectoryInAssetDirectory = trim(str_replace([$assetDirectoryBase, $assetName], '', $assetSource), DS);
        $outputDirectory = $this->getDestinationDirectory($webAssetsDirectoryBase, $webAssetsTemplate, $webAssetsKey);
        $outputRelativeWebPath = $webAssetsTemplate.DS.$webAssetsKey;
        $assetDestinationPath = $outputDirectory.DS.$assetFileDirectoryInAssetDirectory.DS.$assetName;

        Tlog::getInstance()->addDebug("Asset destination full path: $assetDestinationPath");

        if (!file_exists($assetDestinationPath) || ($this->debugMode && ConfigQuery::read('process_assets', true))) {
            Tlog::getInstance()->addDebug("Writing asset to $assetDestinationPath");
            (new Filesystem())->copy($assetSource, $assetDestinationPath, true);
        }

        $outputRelativeWebPath = $this->normalizePath($outputRelativeWebPath);
        $assetFileDirectoryInAssetDirectory = $this->normalizePath($assetFileDirectoryInAssetDirectory);
        $assetName = $this->normalizePath($assetName);

        return rtrim($outputUrl, '/')
            .'/'.trim($outputRelativeWebPath, '/')
            .'/'.trim($assetFileDirectoryInAssetDirectory, '/')
            .'/'.ltrim($assetName, '/');
    }

    private function normalizePath(string $path): string
    {
        return DS !== '/' ? str_replace(DS, '/', $path) : $path;
    }

    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    public function registerAssetFilter(string $filterIdentifier, mixed $filter): void
    {
        $this->assetFilters[$filterIdentifier] = $filter;
    }
}
