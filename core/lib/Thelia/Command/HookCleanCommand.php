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

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Thelia\Core\Event\Cache\CacheEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;

/**
 * Clean hook
 *
 * Class HookCleanCommand
 * @package Thelia\Command
 *
 * @author Julien Chanséaume <julien@thelia.net>
 *
 */
class HookCleanCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName("hook:clean")
            ->setDescription("Clean hooks. It will delete all hooks, then recreate it.")
            ->addOption(
                "assume-yes",
                'y',
                InputOption::VALUE_NONE,
                'Assume to answer yes to all questions'
            )
            ->addArgument(
                "module",
                InputArgument::OPTIONAL,
                "The module code to clean up"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $module = $this->getModule($input);

            if (!$this->askConfirmation($input, $output)) {
                return;
            }

            $this->deleteHooks($module);

            $output->writeln("<info>Hooks have been successfully deleted</info>");

            $this->clearCache($output);
        } catch (\Exception $ex) {
            $output->writeln(sprintf("<error>%s</error>", $ex->getMessage()));
        }

    }

    private function getModule(InputInterface $input)
    {
        $module = null;
        $moduleCode = $input->getArgument("module");

        if (!empty($moduleCode)) {
            if (null === $module = ModuleQuery::create()->findOneByCode($moduleCode)) {
                throw new \RuntimeException(sprintf("Module %s does not exist.", $moduleCode));
            }
        }

        return $module;
    }

    private function askConfirmation(InputInterface $input, OutputInterface $output)
    {
        $assumeYes = $input->getOption("assume-yes");
        $moduleCode = $input->getArgument("module");

        if (!$assumeYes) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $questionText = "Would you like to delete all hooks ";
            $questionText .= (empty($moduleCode))
                ? "of all modules"
                : "of module " . $moduleCode;
            $questionText .= " ? (yes, or no) ";

            $question = new ConfirmationQuestion($questionText, false);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info>No hooks deleted</info>");
                return false;
            }
        }

        return true;
    }

    /**
     * Delete module hooks
     *
     * @param Module|null $module if specified it will only delete hooks related to this module.
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function deleteHooks($module)
    {
        $query = ModuleHookQuery::create();
        if (null !== $module) {
            $query
                ->filterByModule($module)
                ->delete();
        } else {
            $query->deleteAll();
        }

        $query = IgnoredModuleHookQuery::create();
        if (null !== $module) {
            $query
                ->filterByModule($module)
                ->delete();
        } else {
            $query->deleteAll();
        }
    }

    /**
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function clearCache(OutputInterface $output)
    {
        try {
            $cacheDir = $this->getContainer()->getParameter("kernel.cache_dir");
            $cacheEvent = new CacheEvent($cacheDir);
            $this->getDispatcher()->dispatch(TheliaEvents::CACHE_CLEAR, $cacheEvent);
        } catch (\Exception $ex) {
            throw new \Exception(sprintf("Error during clearing of cache : %s", $ex->getMessage()));
        }
    }
}
