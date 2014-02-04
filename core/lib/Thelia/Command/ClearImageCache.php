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
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $request = new Request();

        try {
            $event = new ImageEvent($request);

            $subdir = $input->getArgument('subdir');

            if (! is_null($subdir)) $event->setCacheSubdirectory($subdir);

            $dispatcher->dispatch(TheliaEvents::IMAGE_CLEAR_CACHE, $event);

            $output->writeln(sprintf('%s image cache successfully cleared.', is_null($subdir) ? 'Entire' : ucfirst($subdir)));
        } catch (\Exception $ex) {
             $output->writeln(sprintf("Failed to clear image cache: %s", $ex->getMessage()));
        }
    }
}
