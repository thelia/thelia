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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AdminQuery;
use Thelia\Tools\Password;

/**
 * command line for updating admin password
 *
 * php Thelia admin:updatePassword
 *
 * Class AdminUpdatePasswordCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AdminUpdatePasswordCommand extends ContainerAwareCommand
{

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('admin:updatePassword')
            ->setDescription('change administrator password')
            ->setHelp('The <info>admin:updatePassword</info> command allows you to change the password for a given administrator')
            ->addArgument(
                'login',
                InputArgument::REQUIRED,
                'Login for administrator you want to change the password'
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Desired password. If this option is omitted, a random password is generated and shown in this prompt after'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $login = $input->getArgument('login');

        if (null === $admin = AdminQuery::create()->filterByLogin($login)->findOne()) {
            throw new \RuntimeException(sprintf('Admin with login %s does not exists', $login));
        }

        $password = $input->getOption('password') ?: Password::generateRandom();

        $event = new AdministratorUpdatePasswordEvent($admin);
        $event->setPassword($password);

        $this->
            getContainer()
            ->get('event_dispatcher')
            ->dispatch(TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD, $event);

        $output->writeln(array(
            '',
            sprintf('<info>admin %s password updated</info>', $login),
            sprintf('<info>new password is : %s</info>', $password),
            ''
        ));

    }

}
