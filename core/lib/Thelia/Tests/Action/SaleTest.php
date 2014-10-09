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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Sale;
use Thelia\Core\Event\Sale\SaleActiveStatusCheckEvent;
use Thelia\Core\Event\Sale\SaleClearStatusEvent;
use Thelia\Core\Event\Sale\SaleCreateEvent;
use Thelia\Core\Event\Sale\SaleDeleteEvent;
use Thelia\Core\Event\Sale\SaleToggleActivityEvent;
use Thelia\Core\Event\Sale\SaleUpdateEvent;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;

/**
 * Class SaleTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@thelia.net>
 */
class SaleTest extends TestCaseWithURLToolSetup
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public function getUpdateEvent(&$sale)
    {
        if (!$sale instanceof \Thelia\Model\Sale) {
            $sale = $this->getRandomSale();
        }

        $event = new SaleUpdateEvent($sale->getId());
        $event->setDispatcher($this->dispatcher);
        $event
            ->setActive(1)
            ->setLocale($sale->getLocale())
            ->setTitle($sale->getTitle())
            ->setChapo($sale->getChapo())
            ->setDescription($sale->getDescription())
            ->setPostscriptum($sale->getPostscriptum())
        ;

        return $event;
    }

    public function processUpdateAction($event)
    {
        $saleAction = new Sale($this->getContainer());
        $saleAction->update($event);

        return $event->getSale();
    }

    public function testCreateSale()
    {
        $event = new SaleCreateEvent();
        $event->setDispatcher($this->dispatcher);
        $event
            ->setLocale('en_US')
            ->setTitle('test create sale')
            ->setSaleLabel('test create sale label')
        ;

        $saleAction = new Sale($this->getContainer());
        $saleAction->create($event);

        $createdSale = $event->getSale();

        $this->assertInstanceOf('Thelia\Model\Sale', $createdSale);
        $this->assertEquals('test create sale', $createdSale->getTitle());
        $this->assertEquals('test create sale label', $createdSale->getSaleLabel());
    }

    public function testUpdateSale()
    {
        $sale = $this->getRandomSale();

        $date = new \DateTime();

        $product = ProductQuery::create()->findOne();

        $event = new SaleUpdateEvent($sale->getId());
        $event->setDispatcher($this->dispatcher);
        $event
            ->setStartDate($date->setTimestamp(strtotime("today - 1 month")))
            ->setEndDate($date->setTimestamp(strtotime("today + 1 month")))
            ->setActive(1)
            ->setDisplayInitialPrice(1)
            ->setPriceOffsetType(\Thelia\Model\Sale::OFFSET_TYPE_AMOUNT)
            ->setPriceOffsets([ CurrencyQuery::create()->findOne()->getId() => 10 ])
            ->setProducts([$product->getId()])
            ->setProductAttributes([])
            ->setLocale('en_US')
            ->setTitle('test update sale title')
            ->setChapo('test update sale short description')
            ->setDescription('test update sale description')
            ->setPostscriptum('test update sale postscriptum')
            ->setSaleLabel('test create sale label')
        ;

        $saleAction = new Sale($this->getContainer());
        $saleAction->update($event);

        $updatedSale = $event->getSale();

        $this->assertInstanceOf('Thelia\Model\Sale', $updatedSale);
        $this->assertEquals(1, $updatedSale->getActive());
        $this->assertEquals('test update sale title', $updatedSale->getTitle());
        $this->assertEquals('test update sale short description', $updatedSale->getChapo());
        $this->assertEquals('test update sale description', $updatedSale->getDescription());
        $this->assertEquals('test update sale postscriptum', $updatedSale->getPostscriptum());
        $this->assertEquals('test create sale label', $updatedSale->getSaleLabel());
    }

    public function testUpdatePseSale()
    {
        $sale = $this->getRandomSale();

        $date = new \DateTime();

        $product = ProductQuery::create()->findOne();
        $attrAv = AttributeAvQuery::create()->findOne();

        $event = new SaleUpdateEvent($sale->getId());
        $event->setDispatcher($this->dispatcher);
        $event
            ->setStartDate($date->setTimestamp(strtotime("today - 1 month")))
            ->setEndDate($date->setTimestamp(strtotime("today + 1 month")))
            ->setActive(1)
            ->setDisplayInitialPrice(1)
            ->setPriceOffsetType(\Thelia\Model\Sale::OFFSET_TYPE_AMOUNT)
            ->setPriceOffsets([ CurrencyQuery::create()->findOne()->getId() => 10 ])
            ->setProducts([$product->getId()])
            ->setProductAttributes([$product->getId() => [ $attrAv->getId()] ])
            ->setLocale('en_US')
            ->setTitle('test update sale title')
            ->setChapo('test update sale short description')
            ->setDescription('test update sale description')
            ->setPostscriptum('test update sale postscriptum')
            ->setSaleLabel('test create sale label')
        ;

        $saleAction = new Sale($this->getContainer());
        $saleAction->update($event);

        $updatedSale = $event->getSale();

        $this->assertInstanceOf('Thelia\Model\Sale', $updatedSale);
        $this->assertEquals(1, $updatedSale->getActive());
        $this->assertEquals('test update sale title', $updatedSale->getTitle());
        $this->assertEquals('test update sale short description', $updatedSale->getChapo());
        $this->assertEquals('test update sale description', $updatedSale->getDescription());
        $this->assertEquals('test update sale postscriptum', $updatedSale->getPostscriptum());
        $this->assertEquals('test create sale label', $updatedSale->getSaleLabel());
    }

    public function testDeleteSale()
    {
        $sale = $this->getRandomSale();

        $event = new SaleDeleteEvent($sale->getId());
        $event->setDispatcher($this->dispatcher);

        $saleAction = new Sale($this->getContainer());
        $saleAction->delete($event);

        $deletedSale = $event->getSale();

        $this->assertInstanceOf('Thelia\Model\Sale', $deletedSale);
        $this->assertTrue($deletedSale->isDeleted());
    }

    public function testSaleToggleVisibility()
    {
        $sale = $this->getRandomSale();

        $visibility = $sale->getActive();

        $event = new SaleToggleActivityEvent($sale);
        $event->setDispatcher($this->dispatcher);

        $saleAction = new Sale($this->getContainer());
        $saleAction->toggleActivity($event);

        $updatedSale = $event->getSale();

        $this->assertInstanceOf('Thelia\Model\Sale', $updatedSale);
        $this->assertEquals(!$visibility, $updatedSale->getActive());
    }

    public function testClearAllSales()
    {
        // Store current promo statuses
        $promoList = ProductSaleElementsQuery::create()->filterByPromo(true)->select('Id')->find()->toArray();

        $event = new SaleClearStatusEvent();
        $event->setDispatcher($this->dispatcher);

        $saleAction = new Sale($this->getContainer());
        $saleAction->clearStatus($event);

        // Restore promo status
        ProductSaleElementsQuery::create()->filterById($promoList)->update(['Promo' => true]);
    }

    /**
     * @return \Thelia\Model\Sale
     */
    protected function getRandomSale()
    {
        $sale = SaleQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if (null === $sale) {
            $this->fail('use fixtures before launching test, there is no sale in database');
        }

        return $sale;
    }
}
