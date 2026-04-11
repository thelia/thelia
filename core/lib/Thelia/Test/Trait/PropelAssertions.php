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
 * Every read goes through a fresh query — `clearInstancePool()` is called
 * before any lookup so that data modified by an HTTP request handler (which
 * may have mutated Propel's in-memory object pool) is re-read from the
 * database and not from the pool.
 */
trait PropelAssertions
{
    /**
     * @param class-string $queryClass a Propel *Query class (e.g. ProductQuery::class)
     */
    protected static function assertRowExists(string $queryClass, int $id, ?string $message = null): void
    {
        $queryClass::create()->clearInstancePool();
        $row = $queryClass::create()->findPk($id);

        Assert::assertNotNull(
            $row,
            $message ?? \sprintf('%s row #%d is missing.', $queryClass, $id),
        );
    }

    /**
     * @param class-string $queryClass
     */
    protected static function assertRowDeleted(string $queryClass, int $id, ?string $message = null): void
    {
        $queryClass::create()->clearInstancePool();

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
        $queryClass::create()->clearInstancePool();

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
