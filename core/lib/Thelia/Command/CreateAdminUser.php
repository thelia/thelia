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
            ->setName("thelia:create-admin")
            ->setDescription("Create a new adminsitration user")
            ->setHelp("The <info>thelia:create-admin</info> command create a new administration user.")
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

    protected function enterData($dialog, $output, $label, $error_message)
    {
        return $dialog->askAndValidate(
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

        $admin->setLogin($this->enterData($dialog, $output, "Admin login name : ", "Please enter a login name."));
        $admin->setFirstname($this->enterData($dialog, $output, "User first name : ", "Please enter user first name."));
        $admin->setLastname($this->enterData($dialog, $output, "User last name : ", "Please enter user last name."));

        do {
            $password = $this->enterData($dialog, $output, "Password : ", "Please enter a password.");
            $password_again = $this->enterData($dialog, $output, "Password (again): ", "Please enter the password again.");

            if (! empty($password) && $password == $password_again) {

                $admin->setPassword($password);

                break;
            }

            $output->writeln("Passwords are different, please try again.");
        }
        while (true);

        return $admin;
     }

    protected function decorateInfo($text)
    {
        return sprintf("<info>%s</info>", $text);
    }

}
