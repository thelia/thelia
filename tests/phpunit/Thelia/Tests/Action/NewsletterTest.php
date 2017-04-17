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

use Thelia\Action\Newsletter;
use Thelia\Model\Newsletter as NewsletterModel;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Model\NewsletterQuery;

/**
 * Class NewsletterTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class NewsletterTest extends BaseAction
{
    protected $mailerFactory;
    protected $dispatcher;

    public function setUp()
    {
        $this->mailerFactory = $this->getMockBuilder("Thelia\\Mailer\\MailerFactory")
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->dispatcher = $this->getMockEventDispatcher();
    }

    public static function setUpBeforeClass()
    {
        NewsletterQuery::create()
            ->filterByEmail('test@foo.com')
            ->delete();
    }

    public function testSubscribe()
    {
        $event = new NewsletterEvent('test@foo.com', 'en_US');
        $event
            ->setFirstname("foo")
            ->setLastname("bar")
        ;

        $action = new Newsletter($this->mailerFactory, $this->dispatcher);
        $action->subscribe($event);

        $subscribedNewsletter = $event->getNewsletter();

        $this->assertInstanceOf('Thelia\Model\Newsletter', $subscribedNewsletter);
        $this->assertFalse($subscribedNewsletter->isNew());

        $this->assertEquals('test@foo.com', $subscribedNewsletter->getEmail());
        $this->assertEquals('en_US', $subscribedNewsletter->getLocale());
        $this->assertEquals('foo', $subscribedNewsletter->getFirstname());
        $this->assertEquals('bar', $subscribedNewsletter->getLastname());

        return $subscribedNewsletter;
    }

    /**
     * @param NewsletterModel $newsletter
     * @depends testSubscribe
     * @return NewsletterModel
     */
    public function testUpdate(NewsletterModel $newsletter)
    {
        $event = new NewsletterEvent('test@foo.com', 'en_US');
        $event
            ->setId($newsletter->getId())
            ->setFirstname("foo update")
            ->setLastname("bar update")
        ;

        $action = new Newsletter($this->mailerFactory, $this->dispatcher);
        $action->update($event);

        $updatedNewsletter = $event->getNewsletter();

        $this->assertInstanceOf('Thelia\Model\Newsletter', $updatedNewsletter);

        $this->assertEquals('test@foo.com', $updatedNewsletter->getEmail());
        $this->assertEquals('en_US', $updatedNewsletter->getLocale());
        $this->assertEquals('foo update', $updatedNewsletter->getFirstname());
        $this->assertEquals('bar update', $updatedNewsletter->getLastname());

        return $updatedNewsletter;
    }

    /**
     * @param NewsletterModel $newsletter
     * @depends testUpdate
     * @param NewsletterModel $newsletter
     */
    public function testUnsubscribe(NewsletterModel $newsletter)
    {
        $event = new NewsletterEvent('test@foo.com', 'en_US');
        $event->setId($newsletter->getId());

        $action = new Newsletter($this->mailerFactory, $this->dispatcher);
        $action->unsubscribe($event);

        $deletedNewsletter = $event->getNewsletter();

        $this->assertInstanceOf('Thelia\Model\Newsletter', $deletedNewsletter);
        $this->assertEquals(1, NewsletterQuery::create()->filterByEmail('test@foo.com')->filterByUnsubscribed(true)->count());
    }
}
