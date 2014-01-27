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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\Admin;

class CreateAdminUser extends ContainerAwareCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName("admin:create")
            ->setDescription("Create a new administrator user")
            ->setHelp("The <info>admin:create</info> command create a new administration user.")
            ->addOption(
                'login_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'Admin login name',
                null
            )
            ->addOption(
                'first_name',
                null,
                InputOption::VALUE_OPTIONAL,
                'User first name',
                null
            )
            ->addOption(
                "last_name",
                null,
                InputOption::VALUE_OPTIONAL,
                'User last name',
                null
            )
            ->addOption(
                "locale",
                null,
                InputOption::VALUE_OPTIONAL,
                'Preferred locale (default: en_US)',
                null
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_OPTIONAL,
                'Password',
                null
            )
        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Please enter the admin user information:');

        $admin = $this->getAdminInfo($input, $output); // new Admin();

        $admin->save();

        $output->writeln(array(
                "",
                "<info>User ".$admin->getLogin()." successfully created.</info>",
                ""
            ));
    }

    protected function enterData($dialog, $output, $label, $error_message, $hidden = false)
    {
        $command = $hidden ? 'askHiddenResponse' : 'askAndValidate';

        return $dialog->$command(
            $output,
            $this->decorateInfo($label),
            function ($answer) {
                $answer = trim($answer);
                if (empty($answer)) {
                    throw new \RuntimeException("This information is mandatory.");
                }

                return $answer;
            }
        );
    }

    /**
     * Ask to user all needed information
     *
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return array
     */
    protected function getAdminInfo(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');

        $admin = new Admin();

        $admin->setLogin($input->getOption("login_name") ?: $this->enterData($dialog, $output, "Admin login name : ", "Please enter a login name."));
        $admin->setFirstname($input->getOption("first_name") ?: $this->enterData($dialog, $output, "User first name : ", "Please enter user first name."));
        $admin->setLastname($input->getOption("last_name") ?: $this->enterData($dialog, $output, "User last name : ", "Please enter user last name."));
        $admin->setLocale($input->getOption("locale") ?: 'en_US');

        do {
            $password = $input->getOption("password") ?: $this->enterData($dialog, $output, "Password : ", "Please enter a password.", true);
            $password_again = $input->getOption("password") ?: $this->enterData($dialog, $output, "Password (again): ", "Please enter the password again.", true);

            if (! empty($password) && $password == $password_again) {

                $admin->setPassword($password);

                break;
            }

            $output->writeln("Passwords are different, please try again.");
        } while (true);

        $admin->setProfile(null);

        return $admin;
    }

    protected function decorateInfo($text)
    {
        return sprintf("<info>%s</info>", $text);
    }

}
