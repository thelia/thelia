<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Tests\Action;

use Thelia\Action\Area;
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaCreateEvent;
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\Area\AreaUpdatePostageEvent;
use Thelia\Model\Area as AreaModel;
use Thelia\Model\CountryAreaQuery;
use Thelia\Model\CountryQuery;

/**
 * Class AreaTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AreaTest extends BaseAction
{
    public function testCreate()
    {
        $event = new AreaCreateEvent();
        $event
            ->setAreaName('foo');

        $areaAction = new Area();
        $areaAction->create($event, null, $this->getMockEventDispatcher());

        $createdArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $createdArea);
        $this->assertFalse($createdArea->isNew());
        $this->assertTrue($event->hasArea());

        $this->assertEquals('foo', $createdArea->getName());

        return $createdArea;
    }

    /**
     * @param AreaModel $area
     * @depends testCreate
     * @return AreaModel
     */
    public function testUpdatePostage(AreaModel $area)
    {
        $event = new AreaUpdatePostageEvent($area->getId());
        $event
            ->setPostage(20);

        $areaAction = new Area();
        $areaAction->updatePostage($event, null, $this->getMockEventDispatcher());

        $updatedArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertEquals(20, $updatedArea->getPostage());

        return $updatedArea;
    }

    /**
     * @param AreaModel $area
     * @depends testUpdatePostage
     * @return AreaModel
     */
    public function testAddCountry(AreaModel $area)
    {
        $country = CountryQuery::create()->findOne();

        $event = new AreaAddCountryEvent($area->getId(), [ $country->getId() ]);

        $areaAction = new Area();
        $areaAction->addCountry($event);

        $updatedArea = $event->getArea();

        $updatedCountry = CountryAreaQuery::create()->findOneByAreaId($updatedArea->getId());

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertEquals($country->getId(), $updatedCountry->getCountryId());

        return $updatedArea;
    }

    /**
     * @param AreaModel $area
     * @depends testAddCountry
     * @return AreaModel
     */
    public function testRemoveCountry(AreaModel $area)
    {
        $country = CountryQuery::create()->filterByArea($area)->find()->getFirst();

        $event = new AreaRemoveCountryEvent($area->getId(), $country->getId());

        $areaAction = new Area();
        $areaAction->removeCountry($event);

        $updatedCountry = CountryAreaQuery::create()
            ->filterByCountryId($country->getId())
            ->filterByStateId(null)
            ->filterByAreaId($area->getId())
            ->findOne();

        $updatedArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertNull($updatedCountry);

        return $event->getArea();
    }

    /**
     * @param AreaModel $area
     * @depends testRemoveCountry
     */
    public function testDelete(AreaModel $area)
    {
        $event = new AreaDeleteEvent($area->getId());

        $areaAction = new Area();
        $areaAction->delete($event, null, $this->getMockEventDispatcher());

        $deletedArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $deletedArea);
        $this->assertTrue($deletedArea->isDeleted());
    }
}
