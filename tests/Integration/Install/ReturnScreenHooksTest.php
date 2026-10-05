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
use Thelia\Model\HookI18nQuery;
use Thelia\Model\HookQuery;
use Thelia\Model\Map\HookTableMap;
use Thelia\Test\IntegrationTestCase;

/**
 * The return list and the return sheet of the back office call insertion points
 * that a module can only register on once a `hook` row declares them.
 */
final class ReturnScreenHooksTest extends IntegrationTestCase
{
    private const HOOKS = [
        'order-returns.top',
        'order-returns.bottom',
        'order-returns.js',
        'order-return-edit.top',
        'order-return-edit.bottom',
    ];

    public function testAFreshInstallDeclaresTheHooksOfTheReturnScreens(): void
    {
        self::assertSame(self::HOOKS, $this->declaredHooks());
        self::assertSame(self::HOOKS, $this->hooksTitledIn('en_US'));
        self::assertSame(self::HOOKS, $this->hooksTitledIn('fr_FR'));
    }

    public function testTheUpdateDeclaresThemOnAShopThatHasNoneAndCanBeReplayed(): void
    {
        HookQuery::create()
            ->filterByCode(self::HOOKS)
            ->filterByType(TemplateDefinition::BACK_OFFICE)
            ->delete($this->getPropelConnection());

        self::assertSame([], $this->declaredHooks());

        $this->runHookStatementsOf('3.3.0.sql');
        $this->runHookStatementsOf('3.3.0.sql');

        self::assertSame(self::HOOKS, $this->declaredHooks());

        foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
            self::assertSame(self::HOOKS, $this->hooksTitledIn($locale), $locale);
        }
    }

    /**
     * @return list<string>
     */
    private function hooksTitledIn(string $locale): array
    {
        $titled = [];

        foreach (self::HOOKS as $code) {
            $hook = HookQuery::create()->filterByCode($code)->filterByType(TemplateDefinition::BACK_OFFICE)->findOne();
            $title = null === $hook ? null : HookI18nQuery::create()->filterById($hook->getId())->filterByLocale($locale)->findOne()?->getTitle();

            if (null !== $title && '' !== trim($title)) {
                $titled[] = $code;
            }
        }

        return $titled;
    }

    /**
     * @return list<string>
     */
    private function declaredHooks(): array
    {
        HookTableMap::clearInstancePool();

        $codes = HookQuery::create()
            ->filterByCode(self::HOOKS)
            ->filterByType(TemplateDefinition::BACK_OFFICE)
            ->filterByActivate(true)
            ->select(['Code'])
            ->find()
            ->getData();

        return array_values(array_filter(self::HOOKS, static fn (string $hook): bool => \in_array($hook, $codes, true)));
    }

    private function runHookStatementsOf(string $script): void
    {
        $sql = (string) file_get_contents(THELIA_SETUP_DIRECTORY.'update'.\DIRECTORY_SEPARATOR.'sql'.\DIRECTORY_SEPARATOR.$script);
        $connection = Propel::getWriteConnection(HookTableMap::DATABASE_NAME);
        $run = 0;

        foreach (explode(';', $sql) as $chunk) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $chunk) ?? '');

            if (1 === preg_match('/^INSERT\s+IGNORE\s+INTO\s+`hook(_i18n)?`\s/i', $statement)) {
                $connection->exec($statement);
                ++$run;
            }
        }

        self::assertGreaterThan(0, $run, \sprintf('%s no longer declares any hook.', $script));
    }
}
