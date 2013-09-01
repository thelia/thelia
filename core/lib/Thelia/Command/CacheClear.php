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

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

use Thelia\Command\ContainerAwareCommand;

/**
 * clear the cache
 *
 * Class CacheClear
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 *
 */
class CacheClear extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("cache:clear")
            ->setDescription("Invalidate all caches");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $cacheDir = $this->getContainer()->getParameter("kernel.cache_dir");

        if (!is_writable($cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $cacheDir));
        }

        $output->writeln(sprintf("Clearing cache in <info>%s</info> directory", $cacheDir));

        $fs = new Filesystem();
        try {
            $fs->remove($cacheDir);

            $output->writeln("<info>cache cleared successfully</info>");
        } catch (IOException $e) {
            $output->writeln(sprintf("error during clearing cache : %s", $e->getMessage()));
        }

    }
}
