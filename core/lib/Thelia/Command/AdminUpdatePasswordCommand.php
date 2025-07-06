<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Administrator\AdministratorUpdatePasswordEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\AdminQuery;
use Thelia\Tools\Password;

/**
 * command line for updating admin password.
 *
 * php Thelia admin:updatePassword
 *
 * Class AdminUpdatePasswordCommand
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'admin:updatePassword', description: 'change administrator password')]
class AdminUpdatePasswordCommand extends ContainerAwareCommand
{
    protected function init(): void
    {
        $container = $this->getContainer();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->init();

        $login = $input->getArgument('login');

        if (null === $admin = AdminQuery::create()->filterByLogin($login)->findOne()) {
            throw new \RuntimeException(\sprintf('Admin with login %s does not exists', $login));
        }

        $password = $input->getOption('password') ?: Password::generateRandom();

        $event = new AdministratorUpdatePasswordEvent($admin);
        $event->setPassword($password);

        $this->getDispatcher()->dispatch($event, TheliaEvents::ADMINISTRATOR_UPDATEPASSWORD);

        $output->writeln([
            '',
            \sprintf('<info>admin %s password updated</info>', $login),
            \sprintf('<info>new password is : %s</info>', $password),
            '',
        ]);

        return 0;
    }
}
