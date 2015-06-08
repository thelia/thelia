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

/**
 * clear the cache
 *
 * Class CacheClear
 * @package Thelia\Command
 * @author Manuel Raynaud <manu@thelia.net>
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
                'clear images generated in web/cache directory'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir = $this->getContainer()->getParameter("kernel.cache_dir");

        $this->clearCache($cacheDir, $output);

        if (!$input->getOption("without-assets")) {
            $this->clearCache(THELIA_WEB_DIR . "assets", $output);
        }

        if ($input->getOption('with-images')) {
            $this->clearCache(THELIA_CACHE_DIR, $output);
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
