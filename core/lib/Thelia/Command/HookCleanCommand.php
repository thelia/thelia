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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Hook\ModuleHookConfigurationStore;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;

/**
 * Clean hook.
 *
 * Class HookCleanCommand
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
#[AsCommand(name: 'hook:clean', description: 'Clean hooks. It will delete all hooks, then recreate them from the module declarations.')]
class HookCleanCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp(
                'Delete the module hooks, so that they are recreated from the module declarations on the next '
                ."container build.\n"
                .'The positions and the active states set in the back office are restored on the recreated hooks, '
                .'and the hooks removed in the back office are not restored. Use --reset-positions to drop that '
                .'configuration and start over.',
            )
            ->addOption(
                'assume-yes',
                'y',
                InputOption::VALUE_NONE,
                'Assume to answer yes to all questions',
            )
            ->addOption(
                'reset-positions',
                null,
                InputOption::VALUE_NONE,
                'Do not keep the positions and the active states set in the back office, and restore the hooks removed in the back office',
            )
            ->addArgument(
                'module',
                InputArgument::OPTIONAL,
                'The module code to clean up',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $module = $this->getModule($input);

            if (!$this->askConfirmation($input, $output)) {
                return 1;
            }

            $this->deleteHooks($module, !$input->getOption('reset-positions'));

            $output->writeln('<info>Hooks have been successfully deleted</info>');

            $this->clearCache($output);
        } catch (\Exception $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return 1;
        }

        return 0;
    }

    private function getModule(InputInterface $input)
    {
        $module = null;
        $moduleCode = $input->getArgument('module');

        if (!empty($moduleCode) && null === $module = ModuleQuery::create()->findOneByCode($moduleCode)) {
            throw new \RuntimeException(\sprintf('Module %s does not exist.', $moduleCode));
        }

        return $module;
    }

    private function askConfirmation(InputInterface $input, OutputInterface $output): bool
    {
        $assumeYes = $input->getOption('assume-yes');
        $moduleCode = $input->getArgument('module');

        if (!$assumeYes) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $questionText = 'Would you like to delete all hooks ';
            $questionText .= (empty($moduleCode))
                ? 'of all modules'
                : 'of module '.$moduleCode;
            $questionText .= ' ? (yes, or no) ';

            $question = new ConfirmationQuestion($questionText, false);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<info>No hooks deleted</info>');

                return false;
            }
        }

        return true;
    }

    /**
     * Delete module hooks. They are recreated by RegisterHookListenersPass on the next container build.
     *
     * @param Module|null $module                if specified it will only delete hooks related to this module
     * @param bool        $preserveConfiguration keep the positions and the active states set in the back office,
     *                                           and keep the hooks removed in the back office removed
     *
     * @throws \Exception
     * @throws PropelException
     */
    protected function deleteHooks(?Module $module, bool $preserveConfiguration = true): void
    {
        if ($preserveConfiguration) {
            $savedHooks = ModuleHookQuery::create();

            if ($module instanceof Module) {
                $savedHooks->filterByModule($module);
            }

            ModuleHookConfigurationStore::save($savedHooks->find());
        } else {
            ModuleHookConfigurationStore::discard();
        }

        $query = ModuleHookQuery::create();

        if ($module instanceof Module) {
            $query
                ->filterByModule($module)
                ->delete();
        } else {
            $query->deleteAll();
        }

        if ($preserveConfiguration) {
            // A hook removed in the back office must stay removed.
            return;
        }

        $query = IgnoredModuleHookQuery::create();

        if ($module instanceof Module) {
            $query
                ->filterByModule($module)
                ->delete();
        } else {
            $query->deleteAll();
        }
    }

    /**
     * @throws \Exception
     */
    protected function clearCache(OutputInterface $output): void
    {
        try {
            $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
            $cacheEvent = new CacheEvent($cacheDir);
            $this->getDispatcher()->dispatch($cacheEvent, TheliaEvents::CACHE_CLEAR);
        } catch (\Exception $exception) {
            throw new \Exception(\sprintf('Error during clearing of cache : %s', $exception->getMessage()), $exception->getCode(), $exception);
        }
    }
}
