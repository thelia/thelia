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

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Area;
use Thelia\Model\AreaQuery;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\Event\AreaEvent;
use Thelia\Test\ActionIntegrationTestCase;

final class AreaActionTest extends ActionIntegrationTestCase
{
    public function testCreateAreaThroughGenericSaveEvent(): void
    {
        $area = (new Area())->setName('Test Area');

        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_CREATE);

        $reloaded = AreaQuery::create()->findPk($area->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Test Area', $reloaded->getName());
    }

    public function testAddCountryAttachesCountriesToArea(): void
    {
        $area = $this->persistArea('Europe');
        $country = $this->factory->country();

        $this->dispatch(
            new AreaAddCountryEvent($area, [(string) $country->getId()]),
            TheliaEvents::AREA_ADD_COUNTRY,
        );

        $linked = CountryAreaQuery::create()
            ->filterByAreaId($area->getId())
            ->filterByCountryId($country->getId())
            ->count();
        self::assertSame(1, $linked);
    }

    public function testRemoveCountryDetachesCountryFromArea(): void
    {
        $area = $this->persistArea('France');
        $country = $this->factory->country();

        $this->dispatch(
            new AreaAddCountryEvent($area, [(string) $country->getId()]),
            TheliaEvents::AREA_ADD_COUNTRY,
        );

        $this->dispatch(
            new AreaRemoveCountryEvent($area, [(int) $country->getId()]),
            TheliaEvents::AREA_REMOVE_COUNTRY,
        );

        $remaining = CountryAreaQuery::create()
            ->filterByAreaId($area->getId())
            ->filterByCountryId($country->getId())
            ->count();
        self::assertSame(0, $remaining);
    }

    public function testDeleteRemovesArea(): void
    {
        $area = $this->persistArea('To delete');
        $areaId = $area->getId();

        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_DELETE);

        self::assertNull(AreaQuery::create()->findPk($areaId));
    }

    private function persistArea(string $name): Area
    {
        $area = (new Area())->setName($name);
        $this->dispatch(new AreaEvent($area), TheliaEvents::AREA_CREATE);

        return $area;
    }
}
