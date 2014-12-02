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
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\Console\Input\InputArgument;
use Thelia\Core\Event\TheliaEvents;

class ClearImageCache extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("image-cache:clear")
            ->setDescription("Empty part or whole web space image cache")
            ->addArgument("subdir", InputArgument::OPTIONAL, "Clear only the specified subdirectory")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $request = new Request();

        try {
            $event = new ImageEvent($request);

            $subdir = $input->getArgument('subdir');

            if (! is_null($subdir)) {
                $event->setCacheSubdirectory($subdir);
            }

            $this->getDispatcher()->dispatch(TheliaEvents::IMAGE_CLEAR_CACHE, $event);

            $output->writeln(sprintf('%s image cache successfully cleared.', is_null($subdir) ? 'Entire' : ucfirst($subdir)));
        } catch (\Exception $ex) {
            $output->writeln(sprintf("Failed to clear image cache: %s", $ex->getMessage()));
        }
    }
}
