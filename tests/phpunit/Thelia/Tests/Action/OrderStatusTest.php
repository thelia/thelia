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

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Action\OrderStatus as OrderStatusAction;
use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusDeleteEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;
use Thelia\Model\OrderStatus as OrderStatusModel;

/**
 * Class OrderStatusTest
 * @package Thelia\Tests\Action
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class OrderStatusTest extends TestCaseWithURLToolSetup
{
    /**
     * @param OrderStatusModel $orderStatus
     * @return OrderStatusUpdateEvent
     */
    public function getUpdateEvent(OrderStatusModel $orderStatus)
    {
        $event = new OrderStatusUpdateEvent($orderStatus->getId());
        $event
            ->setLocale($orderStatus->getLocale())
            ->setTitle($orderStatus->getTitle())
            ->setChapo($orderStatus->getChapo())
            ->setDescription($orderStatus->getDescription())
            ->setPostscriptum($orderStatus->getPostscriptum())
            ->setColor($orderStatus->getColor())
            ->setCode($orderStatus->getCode())
        ;

        return $event;
    }

    /**
     * @param OrderStatusUpdateEvent $event
     * @return OrderStatusModel
     */
    public function processUpdateAction($event)
    {
        $orderStatusAction = new OrderStatusAction();
        $orderStatusAction->update($event);

        return $event->getOrderStatus();
    }

    /**
     * test order status creation
     * @covers Thelia\Action\OrderStatus::create
     */
    public function testCreateOrderStatus()
    {
        OrderStatusQuery::create()->filterByCode(['order_status_test', 'order_status_test2'], Criteria::IN)->delete();

        $code = 'order_status_test';

        $event = new OrderStatusCreateEvent();
        $event
            ->setLocale('en_US')
            ->setTitle('order status creation test')
            ->setCode($code);

        $orderStatusAction = new OrderStatusAction();

        $orderStatusAction->create($event);

        $orderStatus = $event->getOrderStatus();

        $this->assertInstanceOf('Thelia\Model\OrderStatus', $orderStatus);
        $this->assertEquals('order status creation test', $orderStatus->getTitle());
        $this->assertEquals($code, $orderStatus->getCode());
    }

    /**
     * test update creation
     * @covers Thelia\Action\OrderStatus::update
     */
    public function testUpdateOrderStatusProtected()
    {
        if (null !== $find = OrderStatusQuery::create()->findOneByCode('paid_force_update')) {
            $find->setCode('paid')->save();
        }

        if (null === OrderStatusQuery::create()->findOneByCode('paid')) {
            $this->fail('It\'s not possible to run the tests, because the order status "paid" not found');
        }

        $event = $this->getUpdateEvent(OrderStatusQuery::create()->findOneByCode('paid'));

        $event->setDescription('test');
        $event->setCode('paid_force_update');

        $orderStatusAction = new OrderStatusAction();
        $orderStatusAction->update($event);

        $orderStatus = $event->getOrderStatus();

        $this->assertInstanceOf('Thelia\Model\OrderStatus', $orderStatus);
        $this->assertEquals('test', $orderStatus->getDescription());
        $this->assertEquals('paid', $orderStatus->getCode());
    }

    /**
     * test update creation
     * @covers Thelia\Action\OrderStatus::update
     * @depends testCreateOrderStatus
     */
    public function testUpdateOrderStatusNotProtected()
    {
        $event = $this->getUpdateEvent(OrderStatusQuery::create()->findOneByCode('order_status_test'));

        $event->setDescription('test');
        $event->setCode('order_status_test2');

        $orderStatusAction = new OrderStatusAction();
        $orderStatusAction->update($event);

        $orderStatus = $event->getOrderStatus();

        $this->assertInstanceOf('Thelia\Model\OrderStatus', $orderStatus);
        $this->assertEquals('test', $orderStatus->getDescription());
        $this->assertEquals('order_status_test2', $orderStatus->getCode());
    }

    /**
     * test order status removal
     * @covers Thelia\Action\OrderStatus::delete
     * @depends testUpdateOrderStatusNotProtected
     */
    public function testDeleteOrderStatus()
    {
        $orderStatus = OrderStatusQuery::create()->findOneByCode('order_status_test2');

        $event = new OrderStatusDeleteEvent($orderStatus->getId());
        $orderStatusAction = new OrderStatusAction();
        $orderStatusAction->delete($event);

        $orderStatus = $event->getOrderStatus();

        $this->assertInstanceOf('Thelia\Model\OrderStatus', $orderStatus);
        $this->assertTrue($orderStatus->isDeleted());
    }

    /**
     * test order status removal
     * @covers Thelia\Action\OrderStatus::delete
     * @depends testUpdateOrderStatusProtected
     */
    public function testDeleteOrderStatusProtected()
    {
        $orderStatus = OrderStatusQuery::create()->findOneByCode('paid');

        $event = new OrderStatusDeleteEvent($orderStatus->getId());
        $orderStatusAction = new OrderStatusAction();

        try {
            $orderStatusAction->delete($event);
            $this->fail("A protected order status has been removed");
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * test order status removal
     * @covers Thelia\Action\OrderStatus::delete
     * @depends testUpdateOrderStatusNotProtected
     */
    public function testDeleteOrderStatusWithOrders()
    {
        $orderStatus = OrderStatusQuery::create()->findOneByCode('paid');

        $event = new OrderStatusDeleteEvent($orderStatus->getId());
        $orderStatusAction = new OrderStatusAction();

        try {
            $orderStatusAction->delete($event);
            $this->fail("A protected order status with orders has been removed");
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * test order status update position
     * @covers Thelia\Action\OrderStatus::updatePosition
     */
    public function testUpdatePositionUp()
    {
        $orderStatus = OrderStatusQuery::create()
            ->filterByPosition(2)
            ->findOne();

        if (null === $orderStatus) {
            $this->fail('Use fixtures before launching test, there is not enough folder in database');
        }

        $newPosition = $orderStatus->getPosition()-1;

        $event = new UpdatePositionEvent($orderStatus->getId(), UpdatePositionEvent::POSITION_UP);

        $orderStatusAction = new OrderStatusAction();
        $orderStatusAction->updatePosition($event, null, $this->getMockEventDispatcher());

        $orderStatusUpdated = OrderStatusQuery::create()->findOneById($orderStatus->getId());

        $this->assertEquals($newPosition, $orderStatusUpdated->getPosition(), sprintf("new position is %d, new position expected is %d for order status %d", $newPosition, $orderStatusUpdated->getPosition(), $orderStatusUpdated->getCode()));
    }
}
