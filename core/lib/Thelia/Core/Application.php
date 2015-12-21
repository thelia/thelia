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

namespace Thelia\Core;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * cli application for Thelia
 * Class Application
 * @package Thelia\Core
 * mfony\Component\HttpFoundation\Session\Session
 */
class Application extends BaseApplication
{
    public $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;

        parent::__construct("Thelia", Thelia::THELIA_VERSION);

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

        /** @var \Symfony\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->setDispatcher($eventDispatcher);

        return parent::doRun($input, $output);
    }

    protected function registerCommands()
    {
        $container = $this->kernel->getContainer();

        foreach ($container->getParameter("command.definition") as $command) {
            $r = new \ReflectionClass($command);

            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $this->add($r->newInstance());
            }
        }
    }
}
