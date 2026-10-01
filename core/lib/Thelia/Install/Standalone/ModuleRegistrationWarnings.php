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

use Thelia\Module\ModuleDescriptor;
use Thelia\Tools\TerminalText;

/**
 * What the install tells the operator once the module table is written: the state it reads
 * back, not the one the descriptors ship, since a row the table already held (the first copy
 * of a module found in two directories) keeps the activation and the mandatory flag it had.
 *
 * @internal
 */
final class ModuleRegistrationWarnings
{
    /**
     * @param list<ModuleDescriptorRecord>                        $records    the descriptors the install read
     * @param array<string, array{activate: int, mandatory: int}> $registered the module table, by code
     *
     * @return list<string>
     */
    public static function describe(array $records, array $registered): array
    {
        $writtenRecords = self::firstCopyOfEachModule($records);

        return [
            ...self::mandatoryModulesLeftInactive($writtenRecords, $registered),
            ...self::requiredModulesLeftInactive($writtenRecords, $registered),
        ];
    }

    /**
     * A module found in both vendor/thelia/modules and local/modules is read, and its SQL
     * applied, from each copy, in the order the directories are given: the first copy creates
     * the row and decides its activation and its mandatory flag, the second only refreshes the
     * namespace and the version. The warnings describe the row, so they read the first copy.
     * No copy is the one that runs everywhere: Model\Module::getModuleDir() prefers
     * local/modules, BaseModule::getModuleDir() prefers vendor/thelia/modules, and
     * module:activate installs an unknown module from both, local/modules first.
     *
     * @param list<ModuleDescriptorRecord> $records
     *
     * @return list<ModuleDescriptorRecord>
     */
    private static function firstCopyOfEachModule(array $records): array
    {
        $firstCopies = [];
        foreach ($records as $record) {
            $firstCopies[$record->code] ??= $record;
        }

        return array_values($firstCopies);
    }

    /**
     * In the back-office, <mandatory> hides the deactivation switch of an active module and
     * forbids deleting the module; it does not keep a module from being registered inactive
     * when its descriptor ships it so, or when the row the table already held is inactive.
     * Either way nothing else would say that a module the shop cannot do without is off.
     *
     * @param list<ModuleDescriptorRecord>                        $records
     * @param array<string, array{activate: int, mandatory: int}> $registered
     *
     * @return list<string>
     */
    private static function mandatoryModulesLeftInactive(array $records, array $registered): array
    {
        $warnings = [];
        foreach ($records as $record) {
            $row = $registered[$record->code] ?? null;

            if (null !== $row && 1 === $row['mandatory'] && 0 === $row['activate']) {
                $warnings[] = \sprintf(ModuleDescriptor::MANDATORY_INACTIVE_WARNING, TerminalText::singleLine($record->code));
            }
        }

        return $warnings;
    }

    /**
     * Registering writes each module on its own: an active module whose <required> module
     * ships inactive, or is held inactive by the row the table already had, is registered
     * active next to an inactive dependency. Only a theme activates the required modules of a module it brings
     * and activates (ModuleManagement::install()); the install does not, so that a module
     * shipped inactive is never switched on without the merchant, and it does not check that
     * the active module runs without the inactive one.
     *
     * @param list<ModuleDescriptorRecord>                        $records
     * @param array<string, array{activate: int, mandatory: int}> $registered
     *
     * @return list<string>
     */
    private static function requiredModulesLeftInactive(array $records, array $registered): array
    {
        $warnings = [];
        foreach ($records as $record) {
            if (1 !== ($registered[$record->code]['activate'] ?? null)) {
                continue;
            }

            foreach ($record->descriptor->required->module ?? [] as $requiredModule) {
                $requiredCode = trim((string) $requiredModule);

                if (0 === ($registered[$requiredCode]['activate'] ?? null)) {
                    $code = TerminalText::singleLine($record->code);
                    $required = TerminalText::singleLine($requiredCode);
                    $warnings[] = \sprintf('%s is registered active but requires %s, which is registered inactive: activate %s from the back-office.', $code, $required, $required);
                }
            }
        }

        return $warnings;
    }
}
