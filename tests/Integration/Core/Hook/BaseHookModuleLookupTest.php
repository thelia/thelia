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

namespace Thelia\Tests\Integration\Core\Hook;

use HookModuleProbe\Hook\FirstProbeHook;
use HookModuleProbe\Hook\SecondProbeHook;
use HookModuleProbe\HookModuleProbe;
use PHPUnit\Framework\Attributes\Test;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A hook resolves the module it belongs to in its constructor. A page builds
 * one hook object per hook class, a dozen of them on a back-office screen, and
 * they all read the same module row.
 */
final class BaseHookModuleLookupTest extends IntegrationTestCase
{
    use RecordsSqlQueries;

    private const MODULE_CODE = 'HookModuleProbe';

    protected function setUp(): void
    {
        parent::setUp();

        require_once THELIA_ROOT.'tests/fixtures/hooks/HookModuleProbe/HookModuleProbe.php';
        require_once THELIA_ROOT.'tests/fixtures/hooks/HookModuleProbe/Hook/FirstProbeHook.php';
        require_once THELIA_ROOT.'tests/fixtures/hooks/HookModuleProbe/Hook/SecondProbeHook.php';

        BaseHook::resetModuleClassNameCache();
    }

    protected function tearDown(): void
    {
        BaseHook::resetModuleClassNameCache();

        parent::tearDown();
    }

    #[Test]
    public function theModuleRowIsReadOnceForEveryHookClassOfTheModule(): void
    {
        $this->createProbeModule();

        $statements = $this->recordSqlQueries(static function (): void {
            new FirstProbeHook();
            new SecondProbeHook();
            new FirstProbeHook();
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'module'),
            'Three hooks of the same module must read its row once.',
        );
    }

    #[Test]
    public function everyHookStillCarriesItsOwnModuleInstance(): void
    {
        $this->createProbeModule();

        $first = new FirstProbeHook();
        $second = new SecondProbeHook();

        self::assertInstanceOf(HookModuleProbe::class, $first->module);
        self::assertInstanceOf(HookModuleProbe::class, $second->module);
        self::assertNotSame($first->module, $second->module);
    }

    #[Test]
    public function aModuleWithNoRowIsNotLookedUpAgain(): void
    {
        self::assertNull(ModuleQuery::create()->findOneByCode(self::MODULE_CODE));

        $statements = $this->recordSqlQueries(static function (): void {
            new FirstProbeHook();
            new SecondProbeHook();
        });

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'module'),
            'A module code with no row must not be looked up once per hook class either.',
        );
        self::assertNull((new FirstProbeHook())->module);
    }

    #[Test]
    public function writingTheModuleRowIsSeenByTheNextHook(): void
    {
        self::assertNull((new FirstProbeHook())->module, 'Nothing is installed under that code yet.');

        $this->createProbeModule();

        self::assertInstanceOf(
            HookModuleProbe::class,
            (new FirstProbeHook())->module,
            'Installing the module must drop the memo the hooks answered from.',
        );
    }

    private function createProbeModule(): void
    {
        (new Module())
            ->setCode(self::MODULE_CODE)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(1)
            ->setFullNamespace(HookModuleProbe::class)
            ->save();
    }
}
