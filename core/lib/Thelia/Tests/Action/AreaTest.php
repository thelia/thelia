<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Tests\Action;

use Thelia\Action\Area;
use Thelia\Core\Event\Area\AreaAddCountryEvent;
use Thelia\Core\Event\Area\AreaCreateEvent;
use Thelia\Core\Event\Area\AreaDeleteEvent;
use Thelia\Core\Event\Area\AreaRemoveCountryEvent;
use Thelia\Core\Event\Area\AreaUpdatePostageEvent;
use Thelia\Model\Area as AreaModel;
use Thelia\Model\CountryQuery;

/**
 * Class AreaTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AreaTest extends \PHPUnit_Framework_TestCase
{

    public function testCreate()
    {
        $event = new AreaCreateEvent();
        $event
            ->setAreaName('foo')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $areaAction = new Area();
        $areaAction->create($event);

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
     */
    public function testUpdatePostage(AreaModel $area)
    {
        $event = new AreaUpdatePostageEvent($area->getId());
        $event
            ->setPostage(20)
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $areaAction = new Area();
        $areaAction->updatePostage($event);

        $updatedArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertEquals(20, $updatedArea->getPostage());

        return $updatedArea;

    }

    /**
     * @param AreaModel $area
     * @depends testUpdatePostage
     */
    public function testAddCountry(AreaModel $area)
    {
        $country = CountryQuery::create()->findOne();

        $event = new AreaAddCountryEvent($area->getId(), $country->getId());
        $event->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $areaAction = new Area();
        $areaAction->addCountry($event);

        $updatedArea = $event->getArea();

        $updatedCountry = CountryQuery::create()->findOneByAreaId($updatedArea->getId());

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertEquals($country->getId(), $updatedCountry->getId());

        return $updatedArea;
    }

    /**
     * @param AreaModel $area
     * @depends testAddCountry
     */
    public function testRemoveCountry(AreaModel $area)
    {
        $country = CountryQuery::create()->filterByArea($area)->find()->getFirst();

        $event = new AreaRemoveCountryEvent($area->getId(), $country->getId());
        $event->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $areaAction = new Area();
        $areaAction->removeCountry($event);

        $updatedCountry = CountryQuery::create()->findPk($country->getId());
        $updatedArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $updatedArea);
        $this->assertNull($updatedCountry->getAreaId());

        return $event->getArea();
    }

    /**
     * @depends testRemoveCountry
     */
    public function testDelete(AreaModel $area)
    {
        $event = new AreaDeleteEvent($area->getId());
        $event->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $areaAction = new Area();
        $areaAction->delete($event);

        $deletedArea = $event->getArea();

        $this->assertInstanceOf('Thelia\Model\Area', $deletedArea);
        $this->assertTrue($deletedArea->isDeleted());
    }

}
