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

use Thelia\Action\Feature;
use Thelia\Core\Event\Feature\FeatureDeleteEvent;
use Thelia\Core\Event\Feature\FeatureUpdateEvent;
use Thelia\Model\Feature as FeatureModel;
use Thelia\Core\Event\Feature\FeatureCreateEvent;

/**
 * Class FeatureTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class FeatureTest extends \PHPUnit_Framework_TestCase
{

    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public function testCreate()
    {
        $event = new FeatureCreateEvent();
        $event
            ->setLocale('en_US')
            ->setTitle('test feature')
            ->setDispatcher($this->dispatcher);

        $action = new Feature();
        $action->create($event);

        $createdFeature = $event->getFeature();

        $this->assertInstanceOf('Thelia\Model\Feature', $createdFeature);

        $this->assertFalse($createdFeature->isNew());

        $this->assertEquals("en_US", $createdFeature->getLocale());
        $this->assertEquals("test feature", $createdFeature->getTitle());

        return $createdFeature;

    }

    /**
     * @param FeatureModel $feature
     * @depends testCreate
     */
    public function testUpdate(FeatureModel $feature)
    {
        $event = new FeatureUpdateEvent($feature->getId());

        $event
            ->setLocale('en_US')
            ->setTitle('test update')
            ->setChapo('test chapo')
            ->setDescription('test description')
            ->setPostscriptum('test postscriptum')
            ->setDispatcher($this->dispatcher);

        $action = new Feature();
        $action->update($event);

        $updatedFeature = $event->getFeature();

        $this->assertInstanceOf('Thelia\Model\Feature', $updatedFeature);

        $this->assertEquals('test update', $updatedFeature->getTitle());
        $this->assertEquals('test chapo', $updatedFeature->getChapo());
        $this->assertEquals('test description', $updatedFeature->getDescription());
        $this->assertEquals('test postscriptum', $updatedFeature->getPostscriptum());
        $this->assertEquals('en_US', $updatedFeature->getLocale());

        return $updatedFeature;
    }

    /**
     * @param FeatureModel $feature
     * @depends testUpdate
     */
    public function testDelete(FeatureModel $feature)
    {
        $event = new FeatureDeleteEvent($feature->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Feature();
        $action->delete($event);

        $deletedFeature = $event->getFeature();

        $this->assertInstanceOf('Thelia\Model\Feature', $deletedFeature);

        $this->assertTrue($deletedFeature->isDeleted());

    }

}
