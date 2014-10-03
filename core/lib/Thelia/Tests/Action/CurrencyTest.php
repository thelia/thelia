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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Action\Currency;
use Thelia\Core\Event\Currency\CurrencyDeleteEvent;
use Thelia\Core\Event\Currency\CurrencyUpdateEvent;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Core\Event\Currency\CurrencyCreateEvent;
use Thelia\Model\CurrencyQuery;
use Thelia\Tests\ContainerAwareTestCase;

/**
 * Class CurrencyTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CurrencyTest extends ContainerAwareTestCase
{
    protected $dispatcher;

    public function setUp()
    {
        parent::setUp();
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
        $currency->setByDefault(0)
            ->save();

        $event = new CurrencyDeleteEvent($currency->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Currency();
        $action->delete($event);

        $deletedCurrency = $event->getCurrency();

        $this->assertInstanceOf('Thelia\Model\Currency', $deletedCurrency);

        $this->assertTrue($deletedCurrency->isDeleted());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage It is not allowed to delete the default currency
     */
    public function testDeleteDefault()
    {
        CurrencyQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->limit(1)
            ->update(array('ByDefault' => true));

        $currency = CurrencyQuery::create()->findOneByByDefault(1);

        $event = new CurrencyDeleteEvent($currency->getId());
        $event->setDispatcher($this->dispatcher);

        $action = new Currency();
        $action->delete($event);

    }

    public static function tearDownAfterClass()
    {
        CurrencyQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->limit(1)
            ->update(array('ByDefault' => true));
    }

    /**
     * Use this method to build the container with the services that you need.
     */
    protected function buildContainer(ContainerBuilder $container)
    {
        // TODO: Implement buildContainer() method.
    }
}
