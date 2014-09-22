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

        $event = new AreaAddCountryEvent($area->getId(), [ $country->getId() ]);
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
