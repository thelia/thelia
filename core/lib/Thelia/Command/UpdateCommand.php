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
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Install\Update;

/**
 * Class UpdateCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class UpdateCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('thelia:update')
            ->setDescription('update your database. Before that you have to update all your files')

        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(array(
            '',
            'Welcome to the update database process',
            '<info>Please wait ...</info>'
        ));

        $update = new Update();

        try {
            $updatedVersions = $update->process();

            $output->writeln(array(
                '',
                '<info>Your database is updated successfully !</info>',
                ''
            ));
        } catch (PropelException $e) {
            $errorMsg = $e->getMessage();

            $output->writeln(array(
                '',
                sprintf('<error>Error during update process with message : %s</error>', $e->getMessage()),
                ''
            ));
        } catch (UpToDateException $e) {
            $output->writeln(array(
                '',
                sprintf('<error>%s</error>', $e->getMessage()),
                ''
            ));
        }
    }
}
