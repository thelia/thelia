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

use Thelia\Action\AttributeAv;
use Thelia\Core\Event\Attribute\AttributeAvCreateEvent;
use Thelia\Core\Event\Attribute\AttributeAvDeleteEvent;
use Thelia\Core\Event\Attribute\AttributeAvUpdateEvent;
use Thelia\Model\AttributeQuery;
use Thelia\Model\AttributeAv as AttributeAvModel;

/**
 * Class AttributeAvTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AttributeAvTest extends BaseAction
{
    public function testCreate()
    {
        $attribute = AttributeQuery::create()->findOne();

        $event = new AttributeAvCreateEvent();

        $event
            ->setAttributeId($attribute->getId())
            ->setLocale('en_US')
            ->setTitle('foo');

        $action = new AttributeAv();
        $action->create($event, null, $this->getMockEventDispatcher());

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
     * @return AttributeAvModel
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
        ;

        $action = new AttributeAv();
        $action->update($event, null, $this->getMockEventDispatcher());

        $updatedAttributeAv = $event->getAttributeAv();

        $this->assertInstanceOf('Thelia\Model\AttributeAv', $updatedAttributeAv);

        $this->assertEquals('bar', $updatedAttributeAv->getTitle());
        $this->assertEquals('bar description', $updatedAttributeAv->getDescription());
        $this->assertEquals('bar chapo', $updatedAttributeAv->getChapo());
        $this->assertEquals('bar postscriptum', $updatedAttributeAv->getPostscriptum());

        return $updatedAttributeAv;
    }

    /**
     * @param AttributeAvModel $attributeAv
     * @depends testUpdate
     */
    public function testDelete(AttributeAvModel $attributeAv)
    {
        $event = new AttributeAvDeleteEvent($attributeAv->getId());

        $action = new AttributeAv();
        $action->delete($event, null, $this->getMockEventDispatcher());

        $deletedAttributeAv = $event->getAttributeAv();

        $this->assertInstanceOf('Thelia\Model\AttributeAv', $deletedAttributeAv);
        $this->assertTrue($deletedAttributeAv->isDeleted());
    }
}
