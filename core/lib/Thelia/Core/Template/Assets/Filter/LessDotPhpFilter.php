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

namespace Thelia\Core\Template\Assets\Filter;

use Assetic\Asset\AssetInterface;
use Assetic\Filter\LessphpFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;

/**
 * Loads LESS files using the oyejorge/less.php PHP implementation of less.
 *
 * @link http://lessphp.gpeasy.com
 *
 * @author David Buchmann <david@liip.ch>
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class LessDotPhpFilter extends LessphpFilter implements EventSubscriberInterface
{
    /** @var string the compiler cache directory */
    private $cacheDir;

    public function __construct($environment = 'prod')
    {
        // Assign and create the cache directory, if required.
        $this->cacheDir = THELIA_CACHE_DIR . $environment . DS . 'less.php';

        if (! is_dir($this->cacheDir)) {
            $fs = new Filesystem();

            $fs->mkdir($this->cacheDir);
        }
    }

    public function filterLoad(AssetInterface $asset)
    {
        $filePath = $asset->getSourceRoot() . DS . $asset->getSourcePath();

        Tlog::getInstance()->addDebug("Starting CSS processing: $filePath...");

        $importDirs = [];

        if ($dir = $asset->getSourceDirectory()) {
            $importDirs[$dir] = '';
        }

        foreach ($this->loadPaths as $loadPath) {
            $importDirs[$loadPath] = '';
        }

        $options = [
            'cache_dir'     => $this->cacheDir,
            'relativeUrls'  => false, // Relative paths in less files will be left unchanged.
            'compress'      => true,
            'import_dirs'   => $importDirs
        ];

        $css_file_name = \Less_Cache::Get([$filePath => ''], $options);

        $content = @file_get_contents($this->cacheDir . DS . $css_file_name);

        if ($content === false) {
            $content = '';

            Tlog::getInstance()->warning("Compilation of $filePath did not generate an output file.");
        }

        $asset->setContent($content);

        Tlog::getInstance()->addDebug("CSS processing done.");
    }

    public function clearCacheDir()
    {
        $fs = new Filesystem();

        $fs->remove($this->cacheDir);
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CACHE_CLEAR => array("clearCacheDir", 128),
        ];
    }
}
