<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Thelia\Command\Install;

/**
 * cli application for Thelia
 * Class Application.
 */
class Application extends BaseApplication
{
    public $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;

        parent::__construct('Thelia', Thelia::THELIA_VERSION);

        $this->kernel->boot();

        $this->getDefinition()->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $kernel->getEnvironment()));
        $this->getDefinition()->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.'));
    }

    public function getKernel()
    {
        return $this->kernel;
    }

    public function getContainer()
    {
        return $this->kernel->getContainer();
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    protected function registerCommands(): void
    {
        if (!Thelia::isInstalled()) {
            $this->add(new Install());

            return;
        }

        $container = $this->kernel->getContainer();

        foreach ($container->getParameter('command.definition') as $commandId) {
            $command = $container->get($commandId);
            $r = new \ReflectionClass($command);

            if (!$r->isSubclassOf(\Symfony\Component\Console\Command\Command::class)) {
                continue;
            }

            if ($r->isAbstract()) {
                continue;
            }

            if (!$r->hasMethod('configure')) {
                continue;
            }

            $this->add($command);
        }
    }
}
