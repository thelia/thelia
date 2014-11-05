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
 * @author Manuel Raynaud <manu@thelia.net>
 */
class NewsletterTest extends \PHPUnit_Framework_TestCase
{
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

        $action = new Newsletter();
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
     * @depends testSubscribe
     */
    public function testUpdate(NewsletterModel $newsletter)
    {
        $event = new NewsletterEvent('test@foo.com', 'en_US');
        $event
            ->setId($newsletter->getId())
            ->setFirstname("foo update")
            ->setLastname("bar update")
        ;

        $action = new Newsletter();
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
     * @depends testUpdate
     */
    public function testUnsubscribe(NewsletterModel $newsletter)
    {
        $event = new NewsletterEvent('test@foo.com', 'en_US');
        $event->setId($newsletter->getId());

        $action = new Newsletter();
        $action->unsubscribe($event);

        $deletedNewsletter = $event->getNewsletter();

        $this->assertInstanceOf('Thelia\Model\Newsletter', $deletedNewsletter);
        $this->assertTrue($deletedNewsletter->isDeleted());
    }
}
