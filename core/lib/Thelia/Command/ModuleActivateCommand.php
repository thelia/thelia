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

namespace Thelia\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * activates a module.
 *
 * Class ModuleActivateCommand
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModuleActivateCommand extends BaseModuleGenerate
{
    protected $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('module:activate')
            ->setDescription('Activates a module')
            ->addOption(
                'with-dependencies',
                null,
                InputOption::VALUE_NONE,
                'activate module recursively'
            )
            ->addOption(
                'silent',
                's',
                InputOption::VALUE_NONE,
                "Don't throw exception on error"
            )
            ->addArgument(
                'module',
                InputArgument::REQUIRED,
                'module to activate'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $moduleCode = $this->formatModuleName($input->getArgument('module'));

            $module = ModuleQuery::create()->findOneByCode($moduleCode);

            if (null === $module) {
                throw new \RuntimeException(sprintf('module %s not found', $moduleCode));
            }

            if ($module->getActivate() == BaseModule::IS_ACTIVATED) {
                throw new \RuntimeException(sprintf('module %s is already actived', $moduleCode));
            }

            try {
                $event = new ModuleToggleActivationEvent($module->getId());
                if ($input->getOption('with-dependencies')) {
                    $event->setRecursive(true);
                }

                $this->eventDispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    sprintf(
                        'Activation fail with Exception : [%d] %s',
                        $e->getCode(),
                        $e->getMessage()
                    )
                );
            }

            // impossible to change output class in CommandTester...
            if (method_exists($output, 'renderBlock')) {
                $output->renderBlock([
                    '',
                    sprintf('Activation succeed for module %s', $moduleCode),
                    '',
                ], 'bg=green;fg=black');
            }
        } catch (\Exception $e) {
            if (!$input->getOption('silent')) {
                throw $e;
            }
        }

        return 0;
    }
}
