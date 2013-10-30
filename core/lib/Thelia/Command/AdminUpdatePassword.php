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
use Symfony\Component\Console\Input\InputOption;


/**
 * command line for updating admin password
 *
 * php Thelia admin:updatePassword
 *
 * Class AdminUpdatePassword
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AdminUpdatePassword extends ContainerAwareCommand
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
}