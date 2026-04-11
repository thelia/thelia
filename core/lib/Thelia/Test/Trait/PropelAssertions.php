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

namespace Thelia\Test\Trait;

use PHPUnit\Framework\Assert;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;

/**
 * Assertions helpers for tests that hit the Propel layer.
 *
 * Tests that share the same Propel connection (which is the case under
 * {@see \Thelia\Test\IntegrationTestCase} and its descendants) see model
 * updates immediately via the instance pool, so there is no need for
 * manual pool clearing in this trait. If a test really needs a fresh
 * read (e.g. cross-connection), it should call
 * `\Thelia\Model\Map\FooTableMap::clearInstancePool()` explicitly.
 */
trait PropelAssertions
{
    /**
     * @param class-string $queryClass a Propel *Query class (e.g. ProductQuery::class)
     */
    protected static function assertRowExists(string $queryClass, int $id, ?string $message = null): void
    {
        Assert::assertNotNull(
            $queryClass::create()->findPk($id),
            $message ?? \sprintf('%s row #%d is missing.', $queryClass, $id),
        );
    }

    /**
     * @param class-string $queryClass
     */
    protected static function assertRowDeleted(string $queryClass, int $id, ?string $message = null): void
    {
        Assert::assertNull(
            $queryClass::create()->findPk($id),
            $message ?? \sprintf('%s row #%d still exists.', $queryClass, $id),
        );
    }

    /**
     * @param class-string $queryClass
     */
    protected static function assertRowCount(int $expected, string $queryClass): void
    {
        Assert::assertSame($expected, $queryClass::create()->count());
    }

    /**
     * Asserts the i18n value of a Propel model once `setLocale()` is switched.
     */
    protected static function assertI18nValue(
        ActiveRecordInterface $model,
        string $locale,
        string $getter,
        string $expected,
    ): void {
        \assert(method_exists($model, 'setLocale'));
        $model->setLocale($locale);

        Assert::assertSame($expected, $model->{$getter}());
    }
}
