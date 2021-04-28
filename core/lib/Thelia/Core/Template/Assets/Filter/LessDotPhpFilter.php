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

namespace Thelia\Core\Template\Assets\Filter;

use Assetic\Contracts\Asset\AssetInterface;
use Assetic\Filter\LessphpFilter;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\TheliaEvents;

/**
 * Loads LESS files using the oyejorge/less.php PHP implementation of less.
 *
 * @see http://lessphp.gpeasy.com
 *
 * @author David Buchmann <david@liip.ch>
 * @author Kris Wallsmith <kris.wallsmith@gmail.com>
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class LessDotPhpFilter extends LessphpFilter implements EventSubscriberInterface
{
    /** @var string the compiler cache directory */
    private $cacheDir;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger, string $kernelEnvironment = 'prod')
    {
        // Assign and create the cache directory, if required.
        $this->cacheDir = THELIA_CACHE_DIR.$kernelEnvironment.DS.'less.php';

        if (!is_dir($this->cacheDir)) {
            $fs = new Filesystem();

            $fs->mkdir($this->cacheDir);
        }
        $this->logger = $logger;
    }

    public function filterLoad(AssetInterface $asset): void
    {
        $filePath = $asset->getSourceRoot().DS.$asset->getSourcePath();

        $this->logger->debug("Starting CSS processing: $filePath...");

        $importDirs = [];

        if ($dir = $asset->getSourceDirectory()) {
            $importDirs[$dir] = '';
        }

        foreach ($this->loadPaths as $loadPath) {
            $importDirs[$loadPath] = '';
        }

        $options = [
            'cache_dir' => $this->cacheDir,
            'relativeUrls' => false, // Relative paths in less files will be left unchanged.
            'compress' => true,
            'import_dirs' => $importDirs,
        ];

        $css_file_name = \Less_Cache::Get([$filePath => ''], $options);

        $content = @file_get_contents($this->cacheDir.DS.$css_file_name);

        if ($content === false) {
            $content = '';

            $this->logger->warning("Compilation of $filePath did not generate an output file.");
        }

        $asset->setContent($content);

        $this->logger->debug('CSS processing done.');
    }

    public function clearCacheDir(): void
    {
        $fs = new Filesystem();

        $fs->remove($this->cacheDir);
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::CACHE_CLEAR => ['clearCacheDir', 128],
        ];
    }
}
