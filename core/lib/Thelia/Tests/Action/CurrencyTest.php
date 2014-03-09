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

use Thelia\Action\Currency;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Model\CurrencyQuery;

/**
 * Class CurrencyTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CurrencyTest extends \PHPUnit_Framework_TestCase
{

    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public function testCreate()
    {
        $event = new CurrencyCreateEvent();

        $event
            ->setCurrencyName('test')
            ->setCode('AZE')
            ->setRate('1.35')
            ->setLocale('en_US')
            ->setSymbol('ù')
            ->setDispatcher($this->dispatcher)
        ;

        $action = new Currency();
        $action->create($event);

        $createdCurrency = $event->getCurrency();

        $this->assertInstanceOf('Thelia\Model\Currency', $createdCurrency);
        $this->assertFalse($createdCurrency->isNew());

        $this->assertEquals('test', $createdCurrency->getName());
        $this->assertEquals('AZE', $createdCurrency->getCode());
        $this->assertEquals('1.35', $createdCurrency->getRate());
        $this->assertEquals('en_US', $createdCurrency->getLocale());
        $this->assertEquals('ù', $createdCurrency->getSymbol());

        return $createdCurrency;
    }

    /**
     * @depends testCreate
     */
    public function testUpdate(CurrencyModel $currency)
    {
        $event = new CurrencyUpdateEvent($currency->getId());

        $event
            ->setCurrencyName('test update')
            ->setCode('AZER')
            ->setRate('2.35')
            ->setLocale('en_US')
            ->setSymbol('ù')
            ->setDispatcher($this->dispatcher)
            ;

        $action = new Currency();
        $action->update($event);

        $updatedCurrency = $event->getCurrency();

        $this->assertInstanceOf('Thelia\Model\Currency', $updatedCurrency);
        $this->assertEquals('test update', $updatedCurrency->getName());
        $this->assertEquals('AZER', $updatedCurrency->getCode());
        $this->assertEquals('2.35', $updatedCurrency->getRate());
        $this->assertEquals('en_US', $updatedCurrency->getLocale());
        $this->assertEquals('ù', $updatedCurrency->getSymbol());

        return $updatedCurrency;
    }

    /**
     * @param CurrencyModel $currency
     * @depends testUpdate
     */
    public function testSetDefault(CurrencyModel $currency)
    {
        $event = new CurrencyUpdateEvent($currency->getId());
        $event
            ->setIsDefault(1)
            ->setDispatcher($this->dispatcher);

        $action = new Currency();
        $action->setDefault($event);

        $updatedCurrency = $event->getCurrency();

        $this->assertInstanceOf('Thelia\Model\Currency', $updatedCurrency);

        $this->assertEquals(1, $updatedCurrency->getByDefault());
        $this->assertEquals(1, CurrencyQuery::create()->filterByByDefault(true)->count());

        return $updatedCurrency;
    }

    /**
     * @param CurrencyModel $currency
     * @depends testSetDefault
     */
    public function testDelete(CurrencyModel $currency)
    {
        $event = new CurrencyDeleteEvent($currency->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Currency();
        $action->delete($event);

        $deletedCurrency = $event->getCurrency();

        $this->assertInstanceOf('Thelia\Model\Currency', $deletedCurrency);

        $this->assertTrue($deletedCurrency->isDeleted());
    }

    public static function tearDownAfterClass()
    {
        CurrencyQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->limit(1)
            ->update(array('ByDefault' => true));
    }
}
