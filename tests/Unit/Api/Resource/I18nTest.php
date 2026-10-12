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

namespace Thelia\Tests\Unit\Api\Resource;

use PHPUnit\Framework\TestCase;
use Thelia\Api\Resource\I18n;

/**
 * An I18n resource is hydrated from an array through its setters.
 */
final class I18nTest extends TestCase
{
    public function testTheConstructorHydratesTheFieldsThatHaveASetter(): void
    {
        $i18n = new I18n(['id' => 12, 'unknown' => 'ignored']);

        self::assertSame(12, $i18n->getId());
    }

    public function testTheIdentifierCanBeSetFromOutside(): void
    {
        $i18n = new I18n();

        self::assertSame($i18n, $i18n->setId(7));
        self::assertSame(7, $i18n->getId());
    }
}
