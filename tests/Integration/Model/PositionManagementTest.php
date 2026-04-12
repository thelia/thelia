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

use Thelia\Model\BrandQuery;
use Thelia\Test\IntegrationTestCase;

final class PositionManagementTest extends IntegrationTestCase
{
    public function testGetNextPositionIncrementsFromLastBrand(): void
    {
        $factory = $this->createFixtureFactory();
        $brand1 = $factory->brand();
        $brand2 = $factory->brand();

        self::assertGreaterThan($brand1->getPosition(), $brand2->getPosition());
    }

    public function testMovePositionUpSwapsWithPreviousItem(): void
    {
        $factory = $this->createFixtureFactory();
        $brand1 = $factory->brand();
        $brand2 = $factory->brand();

        $pos1Before = $brand1->getPosition();
        $pos2Before = $brand2->getPosition();

        $brand2->movePositionUp();

        $brand1Reloaded = BrandQuery::create()->findPk($brand1->getId());
        $brand2Reloaded = BrandQuery::create()->findPk($brand2->getId());

        self::assertSame($pos1Before, $brand2Reloaded->getPosition());
        self::assertSame($pos2Before, $brand1Reloaded->getPosition());
    }

    public function testMovePositionDownSwapsWithNextItem(): void
    {
        $factory = $this->createFixtureFactory();
        $brand1 = $factory->brand();
        $brand2 = $factory->brand();

        $pos1Before = $brand1->getPosition();
        $pos2Before = $brand2->getPosition();

        $brand1->movePositionDown();

        $brand1Reloaded = BrandQuery::create()->findPk($brand1->getId());
        $brand2Reloaded = BrandQuery::create()->findPk($brand2->getId());

        self::assertSame($pos2Before, $brand1Reloaded->getPosition());
        self::assertSame($pos1Before, $brand2Reloaded->getPosition());
    }

    public function testChangeAbsolutePositionMovesToGivenSlot(): void
    {
        $factory = $this->createFixtureFactory();
        $brand1 = $factory->brand();

        $originalPosition = $brand1->getPosition();
        $targetPosition = max(1, $originalPosition - 1);

        $brand1->changeAbsolutePosition($targetPosition);

        $brand1Reloaded = BrandQuery::create()->findPk($brand1->getId());
        self::assertSame($targetPosition, $brand1Reloaded->getPosition());
    }
}
