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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Install\Exception\UpToDateException;
use Thelia\Install\Update;

/**
 * Class UpdateCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <manu@thelia.net>
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
            $update->process();

            $output->writeln(array(
                '',
                '<info>Your database is updated successfully !</info>',
                ''
            ));
        } catch (PropelException $e) {
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
