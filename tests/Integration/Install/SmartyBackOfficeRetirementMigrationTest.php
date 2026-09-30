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

namespace Thelia\Tests\Integration\Install;

use Propel\Runtime\Propel;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ConfigTableMap;
use Thelia\Model\Map\ModuleTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * The statements 3.2.0.sql runs on a shop that still ran the Smarty back office: the
 * shop is switched to default-twig, and the modules that only came with the Smarty
 * back office are turned off before their code leaves vendor.
 */
final class SmartyBackOfficeRetirementMigrationTest extends IntegrationTestCase
{
    private const RETIRED_MODULES = ['TheliaSmarty', 'VirtualProductControl'];

    public function testAShopOnTheSmartyBackOfficeIsSwitchedToTheTwigOne(): void
    {
        ConfigQuery::create()
            ->filterByName(TemplateDefinition::BACK_OFFICE_CONFIG_NAME)
            ->update(['Value' => 'default']);

        $this->runMigration();

        self::assertSame('default-twig', $this->storedAdminTemplate());
    }

    public function testAShopOnAnotherBackOfficeKeepsIt(): void
    {
        ConfigQuery::create()
            ->filterByName(TemplateDefinition::BACK_OFFICE_CONFIG_NAME)
            ->update(['Value' => 'my-back-office']);

        $this->runMigration();

        self::assertSame('my-back-office', $this->storedAdminTemplate());
    }

    public function testTheModulesThatCameWithTheSmartyBackOfficeAreTurnedOff(): void
    {
        foreach (self::RETIRED_MODULES as $code) {
            $this->activeModule($code);
        }

        $this->runMigration();
        $this->runMigration();

        foreach (self::RETIRED_MODULES as $code) {
            self::assertSame(
                0,
                (int) ModuleQuery::create()->findOneByCode($code)?->getActivate(),
                $code.' is still active.',
            );
        }
    }

    private function activeModule(string $code): void
    {
        $module = ModuleQuery::create()->findOneByCode($code);

        if (null === $module) {
            $module = (new Module())
                ->setCode($code)
                ->setFullNamespace($code.'\\'.$code)
                ->setType(BaseModule::CLASSIC_MODULE_TYPE)
                ->setPosition(999);
        }

        $module->setActivate(BaseModule::IS_ACTIVATED)->save();
    }

    private function storedAdminTemplate(): ?string
    {
        ConfigTableMap::clearInstancePool();

        return ConfigQuery::create()
            ->findOneByName(TemplateDefinition::BACK_OFFICE_CONFIG_NAME)
            ?->getStoredValue();
    }

    private function runMigration(): void
    {
        $connection = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME);

        foreach ($this->migrationStatements() as $statement) {
            $connection->exec($statement);
        }

        ModuleTableMap::clearInstancePool();
    }

    /**
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $script = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.'3.2.0.sql');

        $statements = [];

        foreach (explode(";\n", $script) as $chunk) {
            $sql = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (str_contains($sql, "'active-admin-template'") || str_contains($sql, "'TheliaSmarty'")) {
                $statements[] = $sql;
            }
        }

        self::assertCount(2, $statements, 'The 3.2.0 script does not retire the Smarty back office.');

        return $statements;
    }
}
