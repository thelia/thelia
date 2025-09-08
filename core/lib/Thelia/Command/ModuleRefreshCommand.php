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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Module\Exception\InvalidModuleException;
use Thelia\Module\ModuleManagement;

/**
 * Class ModuleRefreshCommand
 * Refresh modules list.
 *
 * @author  Jérôme Billiras <jbilliras@openstudio.fr>
 */
#[AsCommand(name: 'module:refresh', description: 'Refresh modules list')]
class ModuleRefreshCommand extends ContainerAwareCommand
{
    public function __construct(protected ModuleManagement $moduleManagement)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->moduleManagement->updateModules($this->getContainer());
        } catch (InvalidModuleException $ime) {
            throw new \RuntimeException(\sprintf('One or more modules could not be refreshed : %s', $ime->getErrorsAsString("\n")), $ime->getCode(), $ime);
        } catch (\Exception $e) {
            throw new \RuntimeException(\sprintf('Refresh modules list fail with Exception : [%d] %s', $e->getCode(), $e->getMessage()), $e->getCode(), $e);
        }

        $output->writeln('<info>Modules list successfully refreshed</info>');

        return 0;
    }
}
