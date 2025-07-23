<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

/**
 * clear the cache.
 *
 * Class CacheClear
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'cache:clear', description: 'Invalidate all caches')]
class CacheClear extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'without-assets',
                null,
                InputOption::VALUE_NONE,
                'do not clear the assets cache in the web space',
            )
            ->addOption(
                'with-images',
                null,
                InputOption::VALUE_NONE,
                'clear images generated in `image_cache_dir_from_web_root` or web/cache/images directory',
            )
            ->addOption(
                'with-documents',
                null,
                InputOption::VALUE_NONE,
                'clear documents generated in `document_cache_dir_from_web_root` or web/cache/documents directory',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cacheDir = (string) $this->getContainer()->getParameter('kernel.cache_dir');

        $this->clearCache($cacheDir, $output);

        if (!$input->getOption('without-assets')) {
            $this->clearCache(THELIA_WEB_DIR.ConfigQuery::read('asset_dir_from_web_root', 'assets'), $output);
        }

        if ($input->getOption('with-images')) {
            $this->clearCache(
                THELIA_WEB_DIR.ConfigQuery::read(
                    'image_cache_dir_from_web_root',
                    'cache'.DS.'images',
                ),
                $output,
            );
        }

        if ($input->getOption('with-documents')) {
            $this->clearCache(
                THELIA_WEB_DIR.ConfigQuery::read(
                    'document_cache_dir_from_web_root',
                    'cache'.DS.'documents',
                ),
                $output,
            );
        }

        return 0;
    }

    protected function clearCache(string $dir, OutputInterface $output): void
    {
        $output->writeln(\sprintf('Clearing cache in <info>%s</info> directory', $dir));

        try {
            $cacheEvent = new CacheEvent($dir, false);
            $this->getDispatcher()->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
        } catch (\UnexpectedValueException $e) {
            // throws same exception code for does not exist and permission denied ...
            if (!file_exists($dir)) {
                $output->writeln(\sprintf('<info>%s cache dir already cleared</info>', $dir));

                return;
            }

            throw $e;
        } catch (IOException $e) {
            $output->writeln(\sprintf('Error during clearing of cache : %s', $e->getMessage()));
        }

        $output->writeln(\sprintf('<info>%s cache directory cleared successfully</info>', $dir));
    }
}
