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

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

/**
 * clear the cache
 *
 * Class CacheClear
 * @package Thelia\Command
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 */
class CacheClear extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("cache:clear")
            ->setDescription("Invalidate all caches")
            ->addOption(
                "without-assets",
                null,
                InputOption::VALUE_NONE,
                "do not clear the assets cache in the web space"
            )
            ->addOption(
                'with-images',
                null,
                InputOption::VALUE_NONE,
                'clear images generated in `image_cache_dir_from_web_root` or web/cache/images directory'
            )
            ->addOption(
                'with-documents',
                null,
                InputOption::VALUE_NONE,
                'clear documents generated in `document_cache_dir_from_web_root` or web/cache/documents directory'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir = $this->getContainer()->getParameter("kernel.cache_dir");

        $this->clearCache($cacheDir, $output);

        if (!$input->getOption('without-assets')) {
            $this->clearCache(THELIA_WEB_DIR . ConfigQuery::read('asset_dir_from_web_root', 'assets'), $output);
        }

        if ($input->getOption('with-images')) {
            $this->clearCache(
                THELIA_WEB_DIR . ConfigQuery::read(
                    'image_cache_dir_from_web_root',
                    'cache' . DS . 'images'
                ),
                $output
            );
        }

        if ($input->getOption('with-documents')) {
            $this->clearCache(
                THELIA_WEB_DIR . ConfigQuery::read(
                    'document_cache_dir_from_web_root',
                    'cache' . DS . 'documents'
                ),
                $output
            );
        }
    }

    protected function clearCache($dir, OutputInterface $output)
    {
        $output->writeln(sprintf("Clearing cache in <info>%s</info> directory", $dir));

        try {
            $cacheEvent = new CacheEvent($dir);
            $this->getDispatcher()->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
        } catch (\UnexpectedValueException $e) {
            // throws same exception code for does not exist and permission denied ...
            if (!file_exists($dir)) {
                $output->writeln(sprintf("<info>%s cache dir already cleared</info>", $dir));

                return;
            }

            throw $e;
        } catch (IOException $e) {
            $output->writeln(sprintf("Error during clearing of cache : %s", $e->getMessage()));
        }

        $output->writeln(sprintf("<info>%s cache directory cleared successfully</info>", $dir));
    }
}
