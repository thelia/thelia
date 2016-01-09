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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\AdminQuery;
use Thelia\Tools\Password;

/**
 * command line for updating admin password
 *
 * php Thelia admin:updatePassword
 *
 * Class AdminUpdatePasswordCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AdminUpdatePasswordCommand extends ContainerAwareCommand
{
    protected function init(InputInterface $input)
    {
        $container = $this->getContainer();

        $container->set("request", new Request());
        $container->get("request")->setSession(new Session(new MockArraySessionStorage()));
        $container->enterScope("request");
/*
        $this->translator = $container->get('thelia.translator');
        $this->parser = $container->get('thelia.parser');

        $this->con = Propel::getConnection(ProductTableMap::DATABASE_NAME);

        $this->initLocales($input);

        $this->initParser();
*/
    }

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
        $this->init($input);

        $login = $input->getArgument('login');

        if (null === $admin = AdminQuery::create()->filterByLogin($login)->findOne()) {
            throw new \RuntimeException(sprintf('Admin with login %s does not exists', $login));
        }

        $password = $input->getOption('password') ?: Password::generateRandom();

        $event = new AdministratorUpdatePasswordEvent($admin);
        $event->setPassword($password);

        $this->getDispatcher()->dispatch(TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD, $event);

        $output->writeln(array(
            '',
            sprintf('<info>admin %s password updated</info>', $login),
            sprintf('<info>new password is : %s</info>', $password),
            ''
        ));
    }
}
