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

use Thelia\Action\Attribute;
use Thelia\Core\Event\Attribute\AttributeDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeUpdateEvent;
use Thelia\Model\Attribute as AttributeModel;
use Thelia\Core\Event\Attribute\AttributeCreateEvent;

/**
 * Class AttributeTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@thelia.net>
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
