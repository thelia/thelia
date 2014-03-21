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

use Thelia\Action\AttributeAv;
use Thelia\Core\Event\Attribute\AttributeAvCreateEvent;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Model\AttributeQuery;
use Thelia\Model\AttributeAv as AttributeAvModel;

/**
 * Class AttributeAvTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AttributeAvTest extends \PHPUnit_Framework_TestCase
{

    public function testCreate()
    {
        $attribute = AttributeQuery::create()->findOne();

        $event = new AttributeAvCreateEvent();

        $event
            ->setAttributeId($attribute->getId())
            ->setLocale('en_US')
            ->setTitle('foo')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $action = new AttributeAv();
        $action->create($event);

        $attributeAvCreated = $event->getAttributeAv();

        $this->assertInstanceOf('Thelia\Model\AttributeAv', $attributeAvCreated);

        $this->assertEquals('en_US', $attributeAvCreated->getLocale());
        $this->assertEquals('foo', $attributeAvCreated->getTitle());
        $this->assertNull($attributeAvCreated->getDescription());
        $this->assertNull($attributeAvCreated->getPostscriptum());
        $this->assertNull($attributeAvCreated->getChapo());
        $this->assertEquals($attribute, $attributeAvCreated->getAttribute());

        return $attributeAvCreated;
    }

    /**
     * @param AttributeAvModel $attributeAv
     * @depends testCreate
     */
    public function testUpdate(AttributeAvModel $attributeAv)
    {
        $event = new AttributeAvUpdateEvent($attributeAv->getId());

        $event
            ->setLocale($attributeAv->getLocale())
            ->setTitle('bar')
            ->setDescription('bar description')
            ->setChapo('bar chapo')
            ->setPostscriptum('bar postscriptum')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"))
        ;

        $action = new AttributeAv();
        $action->update($event);

        $updatedAttributeAv = $event->getAttributeAv();

        $this->assertInstanceOf('Thelia\Model\AttributeAv', $updatedAttributeAv);

        $this->assertEquals('bar', $updatedAttributeAv->getTitle());
        $this->assertEquals('bar description', $updatedAttributeAv->getDescription());
        $this->assertEquals('bar chapo', $updatedAttributeAv->getChapo());
        $this->assertEquals('bar postscriptum', $updatedAttributeAv->getPostscriptum());

        return $updatedAttributeAv;
    }

    /**
     * @depends testUpdate
     */
    public function testDelete(AttributeAvModel $attributeAv)
    {
        $event = new AttributeAvDeleteEvent($attributeAv->getId());
        $event->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $action = new AttributeAv();
        $action->delete($event);

        $deletedAttributeAv = $event->getAttributeAv();

        $this->assertInstanceOf('Thelia\Model\AttributeAv', $deletedAttributeAv);
        $this->assertTrue($deletedAttributeAv->isDeleted());
    }

}
