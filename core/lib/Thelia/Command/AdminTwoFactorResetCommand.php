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
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Admin\TwoFactor\AdminTwoFactorManager;
use Thelia\Model\AdminQuery;

#[AsCommand(name: 'admin:two-factor:reset', description: 'Remove the second factor of an administrator who lost their phone and their backup codes.')]
class AdminTwoFactorResetCommand extends ContainerAwareCommand
{
    public function __construct(protected AdminTwoFactorManager $twoFactorManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'The administrator signs in with their password alone afterwards, and enables a new second factor from the back office. '
                .'The reset is written to the admin log.',
            )
            ->addArgument('login', InputArgument::REQUIRED, 'Login of the administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $login = (string) $input->getArgument('login');
        $admin = AdminQuery::create()->findOneByLogin($login);

        if (null === $admin) {
            $output->writeln(\sprintf('<error>No administrator has the login %s.</error>', $login));

            return self::FAILURE;
        }

        if (!$this->twoFactorManager->isEnabledFor($admin)) {
            $output->writeln(\sprintf('<info>Administrator %s has no second factor.</info>', $login));

            return self::SUCCESS;
        }

        $this->twoFactorManager->resetFromCommandLine($admin);
        $output->writeln(\sprintf('<info>The second factor of administrator %s is removed.</info>', $login));

        return self::SUCCESS;
    }
}
