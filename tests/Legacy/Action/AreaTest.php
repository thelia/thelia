<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use Thelia\Action\Area;
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Model\Area as AreaModel;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CountryQuery;

/**
 * Class AreaTest.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaTest extends BaseAction
{
    public function testCreate()
    {
        $area = (new AreaModel())
            ->setName('foo');

        $area->save();

        $this->assertInstanceOf('Thelia\Model\Area', $area);
        $this->assertFalse($area->isNew());

        $this->assertEquals('foo', $area->getName());

        return $area;
    }

    /**
     * @depends testCreate
     *
     * @return AreaModel
     */
    public function testAddCountry(AreaModel $area)
    {
        $this->markTestSkipped('Area country doesn\'t work like this anymore');

        $country = CountryQuery::create()->findOne();

        $event = new AreaAddCountryEvent($area, $country->getId());

        $areaAction = new Area();
        $areaAction->addCountry($event);

        $updatedArea = $event->getModel();

        $updatedCountry = CountryAreaQuery::create()->findOneByAreaId($updatedArea->getId());

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertEquals($country->getId(), $updatedCountry->getCountryId());

        return $updatedArea;
    }

    /**
     * @depends testAddCountry
     *
     * @return AreaModel
     */
    public function testRemoveCountry(AreaModel $area)
    {
        $this->markTestSkipped('Area country doesn\'t work like this anymore');

        $country = CountryQuery::create()
            ->useCountryAreaQuery()
                ->filterByArea($area)
            ->endUse()
            ->find()
            ->getFirst();

        $event = new AreaRemoveCountryEvent($area, $country->getId());

        $areaAction = new Area();
        $areaAction->removeCountry($event);

        $updatedCountry = CountryAreaQuery::create()
            ->filterByCountryId($country->getId())
            ->filterByStateId(null)
            ->filterByAreaId($area->getId())
            ->findOne();

        $updatedArea = $event->getModel();

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertNull($updatedCountry);

        return $event->getModel();
    }

    /**
     * @depends testRemoveCountry
     */
    public function testDelete(AreaModel $area)
    {
        $area->delete();

        $this->assertInstanceOf('Thelia\Model\Area', $area);
        $this->assertTrue($area->isDeleted());
    }
}
