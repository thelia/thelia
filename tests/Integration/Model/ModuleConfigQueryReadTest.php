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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\Test;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A module reads its configuration wherever it needs it, and a page asks for
 * the same handful of names over and over.
 */
final class ModuleConfigQueryReadTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $module = (new Module())
            ->setCode('ModuleConfigReadProbe')
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(1)
            ->setFullNamespace('ModuleConfigReadProbe\\ModuleConfigReadProbe');
        $module->save();

        $this->moduleId = $module->getId();

        ModuleConfigQuery::create()->setConfigValue($this->moduleId, 'title', 'Shop title');
        ModuleConfigQuery::create()->setConfigValue($this->moduleId, 'subtitle', 'Shop subtitle');

        ModuleConfigQuery::resetConfigCache();
    }

    protected function tearDown(): void
    {
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    #[Test]
    public function theConfigurationOfAModuleIsReadInOneGo(): void
    {
        $moduleId = $this->moduleId;

        $statements = $this->recordSqlQueries(static function () use ($moduleId): void {
            for ($read = 0; $read < 3; ++$read) {
                ModuleConfigQuery::create()->getConfigValue($moduleId, 'title');
                ModuleConfigQuery::create()->getConfigValue($moduleId, 'subtitle');
                ModuleConfigQuery::create()->getConfigValue($moduleId, 'absent');
            }
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'module_config'),
            'Nine reads of three names must cost one read of the module configuration.',
        );
    }

    #[Test]
    public function theValuesAreTheOnesStored(): void
    {
        self::assertSame('Shop title', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'));
        self::assertSame('Shop subtitle', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'subtitle'));
        self::assertNull(ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'absent'));
        self::assertSame('fallback', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'absent', 'fallback'));
    }

    #[Test]
    public function eachLocaleAnswersWithItsOwnValue(): void
    {
        ModuleConfigQuery::create()->setConfigValue($this->moduleId, 'title', 'Titre de la boutique', 'fr_FR');

        self::assertSame(
            'Titre de la boutique',
            ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title', null, 'fr_FR'),
        );
        self::assertSame(
            'Shop title',
            ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'),
            'A read naming no locale must not inherit the one a previous read asked for.',
        );
        self::assertSame(
            'Titre de la boutique',
            ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title', null, 'fr_FR'),
        );
    }

    #[Test]
    public function writingAValueIsSeenByTheNextRead(): void
    {
        self::assertSame('Shop title', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'));

        ModuleConfigQuery::create()->setConfigValue($this->moduleId, 'title', 'Another title');

        self::assertSame('Another title', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'));
    }

    #[Test]
    public function creatingAValueIsSeenByTheNextRead(): void
    {
        self::assertNull(ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'created_later'));

        ModuleConfigQuery::create()->setConfigValue($this->moduleId, 'created_later', 'now there');

        self::assertSame('now there', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'created_later'));
    }

    #[Test]
    public function deletingAValueIsSeenByTheNextRead(): void
    {
        self::assertSame('Shop title', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'));

        ModuleConfigQuery::create()->deleteConfigValue($this->moduleId, 'title');

        self::assertNull(ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'));
    }

    #[Test]
    public function aWriteStraightOnTheModelIsSeenByTheNextRead(): void
    {
        self::assertSame('Shop title', ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'));

        $row = ModuleConfigQuery::create()
            ->filterByModuleId($this->moduleId)
            ->filterByName('title')
            ->findOne();
        $row->setValue('Written on the model')->save();

        self::assertSame(
            'Written on the model',
            ModuleConfigQuery::create()->getConfigValue($this->moduleId, 'title'),
        );
    }
}
