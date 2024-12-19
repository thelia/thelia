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

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Module;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Deactivates a module.
 *
 * Class ModuleDeactivateCommand
 *
 * @author Nicolas Villa <nicolas@libre-shop.com>
 */
class ModuleDeactivateCommand extends BaseModuleGenerate
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
            ->setName('module:deactivate')
            ->setDescription('Deactivate a module')
            ->addOption(
                'with-dependencies',
                null,
                InputOption::VALUE_NONE,
                'activate module recursively'
            )
            ->addArgument(
                'module',
                InputArgument::REQUIRED,
                'module to deactivate'
            )
            ->addOption(
                'assume-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assume to deactivate a mandatory module'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $moduleCode = $this->formatModuleName($input->getArgument('module'));

        $module = ModuleQuery::create()->findOneByCode($moduleCode);

        if (null === $module) {
            throw new \RuntimeException(sprintf('module %s not found', $moduleCode));
        }

        if ($module->getActivate() == BaseModule::IS_NOT_ACTIVATED) {
            throw new \RuntimeException(sprintf('module %s is already deactivated', $moduleCode));
        }

        try {
            $event = new ModuleToggleActivationEvent($module->getId());

            $module = ModuleQuery::create()->findPk($module->getId());
            if ($module->getMandatory() == BaseModule::IS_MANDATORY) {
                if (!$this->askConfirmation($input, $output)) {
                    return 1;
                }
                $event->setAssumeDeactivate(true);
            }

            if ($input->getOption('with-dependencies')) {
                $event->setRecursive(true);
            }
            $this->eventDispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Deactivation fail with Exception : [%d] %s', $e->getCode(), $e->getMessage()));
        }

        // impossible to change output class in CommandTester...
        if (method_exists($output, 'renderBlock')) {
            $output->renderBlock([
                '',
                sprintf('Deactivation succeed for module %s', $moduleCode),
                '',
            ], 'bg=green;fg=black');
        }

        return 0;
    }

    private function askConfirmation(InputInterface $input, OutputInterface $output)
    {
        $assumeYes = $input->getOption('assume-yes');
        $moduleCode = $input->getArgument('module');

        if (!$assumeYes) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $questionText = 'Module ';
            $questionText .= (empty($moduleCode))
                ? ''
                : $moduleCode;
            $questionText .= " is mandatory.\n";
            $questionText .= 'Would you like to deactivate the module ';
            $questionText .= (empty($moduleCode))
                ? ''
                : $moduleCode;
            $questionText .= ' ? (yes, or no) ';

            $question = new ConfirmationQuestion($questionText, false);

            if (!$helper->ask($input, $output, $question)) {
                return false;
            }
        }

        return true;
    }
}
