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

use Thelia\Action\Message;
use Thelia\Core\Event\Message\MessageDeleteEvent;
use Thelia\Core\Event\Message\MessageUpdateEvent;
use Thelia\Model\Message as MessageModel;
use Thelia\Core\Event\Message\MessageCreateEvent;
use Thelia\Model\MessageQuery;

/**
 * Class MessageTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@thelia.net>
 */
class MessageTest extends \PHPUnit_Framework_TestCase
{
    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public static function setUpBeforeClass()
    {
        $lang = MessageQuery::create()
            ->filterByName('test')
            ->delete();
    }

    public function testCreate()
    {
        $event = new MessageCreateEvent();
        $event
            ->setMessageName('test')
            ->setLocale('en_US')
            ->setTitle('test title')
            ->setSecured(0)
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Message();
        $action->create($event);

        $createdMessage = $event->getMessage();

        $this->assertInstanceOf('Thelia\Model\Message', $createdMessage);
        $this->assertFalse($createdMessage->isNew());

        $this->assertEquals('test', $createdMessage->getName());
        $this->assertEquals('en_US', $createdMessage->getLocale());
        $this->assertEquals('test title', $createdMessage->getTitle());
        $this->assertEquals(0, $createdMessage->getSecured());

        return $createdMessage;
    }

    /**
     * @depends testCreate
     */
    public function testModify(MessageModel $message)
    {
        $event = new MessageUpdateEvent($message->getId());

        $event
            ->setMessageName('test')
            ->setLocale('en_us')
            ->setTitle('test update title')
            ->setSubject('test subject')
            ->setHtmlMessage('my html message')
            ->setTextMessage('my text message')
            ->setHtmlLayoutFileName(null)
            ->setHtmlTemplateFileName(null)
            ->setTextLayoutFileName(null)
            ->setTextTemplateFileName(null)
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Message();
        $action->modify($event);

        $updatedMessage = $event->getMessage();

        $this->assertInstanceOf('Thelia\Model\Message', $updatedMessage);

        $this->assertEquals('test', $updatedMessage->getName());
        $this->assertEquals('en_US', $updatedMessage->getLocale());
        $this->assertEquals('test update title', $updatedMessage->getTitle());
        $this->assertEquals('test subject', $updatedMessage->getSubject());
        $this->assertEquals('my html message', $updatedMessage->getHtmlMessage());
        $this->assertEquals('my text message', $updatedMessage->getTextMessage());
        $this->assertNull($updatedMessage->getHtmlLayoutFileName());
        $this->assertNull($updatedMessage->getHtmlTemplateFileName());
        $this->assertNull($updatedMessage->getTextLayoutFileName());
        $this->assertNull($updatedMessage->getTextTemplateFileName());

        return $updatedMessage;
    }

    /**
     * @depends testModify
     */
    public function testDelete(MessageModel $message)
    {
        $event = new MessageDeleteEvent($message->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Message();
        $action->delete($event);

        $deletedMessage = $event->getMessage();

        $this->assertInstanceOf('Thelia\Model\Message', $deletedMessage);
        $this->assertTrue($deletedMessage->isDeleted());
    }
}
