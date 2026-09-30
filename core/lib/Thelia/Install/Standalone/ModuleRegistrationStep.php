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

namespace Thelia\Install\Standalone;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Module\Exception\InvalidModuleDescriptorException;

/**
 * The two module steps of thelia:install: checking every descriptor before anything is
 * created, and registering the modules once the core schema exists. bin/install and
 * bin/test-prepare do not go through it: they call ModuleDescriptorReader::read() and
 * DatabaseSetup::registerAndApplyModules() themselves, print to STDERR without console
 * styles, and a change here is carried to both scripts.
 */
final readonly class ModuleRegistrationStep
{
    /**
     * @param string[] $moduleDirectories
     */
    public function __construct(
        private array $moduleDirectories = [THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR],
    ) {
    }

    /**
     * @return bool false when a descriptor is refused; the install stops before the database
     *              is created
     */
    public function check(OutputInterface $output): bool
    {
        try {
            (new ModuleDescriptorReader())->read($this->moduleDirectories);
        } catch (InvalidModuleDescriptorException $exception) {
            $output->writeln(\sprintf('<error>ERROR: %s</error>', OutputFormatter::escape($exception->getMessage())));

            return false;
        }

        return true;
    }

    /**
     * Register every module found on disk into the module table, active unless its
     * descriptor says otherwise, and apply their SQL schemas. Without this step the module
     * table stays empty after installation: PropelInitService would then fall back to a full
     * filesystem scan on every boot, and the shop would run with no active module.
     *
     * @param DatabaseSetup $setup a setup already connected to the shop database
     *
     * @return bool false when a descriptor is refused
     */
    public function register(DatabaseSetup $setup, OutputInterface $output): bool
    {
        $output->writeln('<info>Registering modules...</info>');

        try {
            $count = $setup->registerAndApplyModules($this->moduleDirectories);
        } catch (InvalidModuleDescriptorException $exception) {
            $output->writeln(\sprintf('<error>ERROR: %s</error>', OutputFormatter::escape($exception->getMessage())));

            return false;
        }

        $output->writeln(\sprintf('<info>%d module(s) registered</info>', $count));

        foreach ($setup->getWarnings() as $warning) {
            $output->writeln(\sprintf('<comment>WARN %s</comment>', OutputFormatter::escape($warning)));
        }

        return true;
    }
}
