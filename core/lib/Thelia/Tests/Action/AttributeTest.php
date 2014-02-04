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

use Thelia\Action\Attribute;
use Thelia\Core\Event\Attribute\AttributeDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeUpdateEvent;
use Thelia\Model\Attribute as AttributeModel;
use Thelia\Core\Event\Attribute\AttributeCreateEvent;


/**
 * Class AttributeTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AttributeTest extends \PHPUnit_Framework_TestCase
{

    public function testCreateSimple()
    {
        $event = new AttributeCreateEvent();

        $event
            ->setLocale('en_US')
            ->setTitle('foo')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $action = new Attribute();
        $action->create($event);

        $createdAttribute = $event->getAttribute();

        $this->assertInstanceOf('Thelia\Model\Attribute', $createdAttribute);
        $this->assertEquals($createdAttribute->getLocale(), 'en_US');
        $this->assertEquals($createdAttribute->getTitle(), 'foo');

        return $createdAttribute;

    }

    /**
     * @param AttributeModel $attribute
     * @depends testCreateSimple
     */
    public function testUpdate(AttributeModel $attribute)
    {
        $event = new AttributeUpdateEvent($attribute->getId());

        $event
            ->setLocale($attribute->getLocale())
            ->setTitle('bar')
            ->setDescription('bar description')
            ->setChapo('bar chapo')
            ->setPostscriptum('bar postscriptum')
            ->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $action = new Attribute();
        $action->update($event);

        $updatedAttribute = $event->getAttribute();

        $this->assertInstanceOf('Thelia\Model\Attribute', $updatedAttribute);
        $this->assertEquals('en_US', $updatedAttribute->getLocale());
        $this->assertEquals('bar', $updatedAttribute->getTitle());
        $this->assertEquals('bar description', $updatedAttribute->getDescription());
        $this->assertEquals('bar chapo', $updatedAttribute->getChapo());
        $this->assertEquals('bar postscriptum', $updatedAttribute->getPostscriptum());

        return $updatedAttribute;
    }

    /**
     * @depends testUpdate
     */
    public function testDelete(AttributeModel $attribute)
    {
        $event = new AttributeDeleteEvent($attribute->getId());
        $event->setDispatcher($this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface"));

        $action = new Attribute();
        $action->delete($event);

        $deletedAttribute = $event->getAttribute();

        $this->assertInstanceOf('Thelia\Model\Attribute', $deletedAttribute);
        $this->assertTrue($deletedAttribute->isDeleted());
    }

} 