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

use Thelia\Action\Newsletter;
use Thelia\Model\Newsletter as NewsletterModel;
use Thelia\Core\Event\Newsletter\NewsletterEvent;
use Thelia\Model\NewsletterQuery;


/**
 * Class NewsletterTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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