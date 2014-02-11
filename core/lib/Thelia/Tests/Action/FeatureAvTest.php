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

use Thelia\Action\FeatureAv;
use Thelia\Core\Event\Feature\FeatureAvCreateEvent;
use Thelia\Core\Event\Feature\FeatureAvDeleteEvent;
use Thelia\Core\Event\Feature\FeatureAvUpdateEvent;
use Thelia\Model\FeatureAv as FeatureAvModel;
use Thelia\Model\FeatureQuery;


/**
 * Class FeatureAvTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class FeatureAvTest extends \PHPUnit_Framework_TestCase
{
    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    /**
     * @return \Thelia\Model\Feature
     */
    protected function getRandomFeature()
    {
        $feature = FeatureQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if (null === $feature) {
            $this->fail('use fixtures before launching test, there is no feature in database');
        }

        return $feature;
    }

    public function testCreate()
    {
        $feature = $this->getRandomFeature();

        $event = new FeatureAvCreateEvent();
        $event
            ->setFeatureId($feature->getId())
            ->setLocale('en_US')
            ->setTitle('test')
            ->setDispatcher($this->dispatcher);

        $action = new FeatureAv();
        $action->create($event);

        $createdFeatureAv = $event->getFeatureAv();

        $this->assertInstanceOf('Thelia\Model\FeatureAv', $createdFeatureAv);

        $this->assertFalse($createdFeatureAv->isNew());

        $this->assertEquals('en_US', $createdFeatureAv->getLocale());
        $this->assertEquals('test', $createdFeatureAv->getTitle());
        $this->assertEquals($feature->getId(), $createdFeatureAv->getFeatureId());

        return $createdFeatureAv;
    }

    /**
     * @param FeatureAvModel $featureAv
     * @depends testCreate
     */
    public function testUpdate(FeatureAvModel $featureAv)
    {
        $event = new FeatureAvUpdateEvent($featureAv->getId());
        $event
            ->setLocale('en_uS')
            ->setTitle('test update')
            ->setDescription('test description')
            ->setChapo('test chapo')
            ->setPostscriptum('test postscriptum')
            ->setDispatcher($this->dispatcher);

        $action = new FeatureAv();
        $action->update($event);

        $updatedFeatureAv = $event->getFeatureAv();

        $this->assertInstanceOf('Thelia\Model\FeatureAv', $updatedFeatureAv);

        $this->assertEquals('en_US', $updatedFeatureAv->getLocale());
        $this->assertEquals('test update', $updatedFeatureAv->getTitle());
        $this->assertEquals('test chapo', $updatedFeatureAv->getChapo());
        $this->assertEquals('test description', $updatedFeatureAv->getDescription());
        $this->assertEquals('test postscriptum', $updatedFeatureAv->getPostscriptum());

        return $updatedFeatureAv;
    }

    /**
     * @param FeatureAvModel $featureAv
     * @depends testUpdate
     */
    public function testDelete(FeatureAvModel $featureAv)
    {
        $event = new FeatureAvDeleteEvent($featureAv->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new FeatureAv();
        $action->delete($event);

        $deletedFeatureAv = $event->getFeatureAv();

        $this->assertInstanceOf('Thelia\Model\FeatureAv', $deletedFeatureAv);

        $this->assertTrue($deletedFeatureAv->isDeleted());
    }
} 