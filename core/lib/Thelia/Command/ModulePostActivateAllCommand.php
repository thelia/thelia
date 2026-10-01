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

use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

#[AsCommand(name: 'module:post-activate-all', description: 'Run postActivation() for all active modules')]
class ModulePostActivateAllCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = ModuleQuery::create()
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->find();

        $con = Propel::getConnection();
        $count = 0;
        $failedModuleCodes = [];

        foreach ($modules as $module) {
            $code = $module->getCode();

            try {
                $instance = $module->createInstance();

                if (!$instance instanceof BaseModule) {
                    continue;
                }

                $instance->setContainer($this->getApplication()->getKernel()->getContainer());
                $instance->postActivation($con);
                ++$count;
            } catch (\Throwable $e) {
                $failedModuleCodes[] = $code;
                $output->writeln(\sprintf('  <error>%s: %s</error>', $code, $e->getMessage()));
            }
        }

        $output->writeln(\sprintf('%d module(s) post-activated.', $count));

        // A failing module does not stop the others, but the command fails: an
        // install must not report success with a module left half set up.
        if ([] !== $failedModuleCodes) {
            $output->writeln(\sprintf(
                '<error>Post-activation failed for %d module(s): %s.</error>',
                \count($failedModuleCodes),
                implode(', ', $failedModuleCodes),
            ));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
