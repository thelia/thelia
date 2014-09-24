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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Brand;
use Thelia\Core\Event\Brand\BrandCreateEvent;
use Thelia\Core\Event\Brand\BrandDeleteEvent;
use Thelia\Core\Event\Brand\BrandToggleVisibilityEvent;
use Thelia\Core\Event\Brand\BrandUpdateEvent;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\BrandQuery;
use Thelia\Tests\TestCaseWithURLToolSetup;

/**
 * Class BrandTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class BrandTest extends TestCaseWithURLToolSetup
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function setUp()
    {
        $this->dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");
    }

    public function getUpdateEvent(&$brand)
    {
        if (!$brand instanceof \Thelia\Model\Brand) {
            $brand = $this->getRandomBrand();
        }

        $event = new BrandUpdateEvent($brand->getId());
        $event->setDispatcher($this->dispatcher);
        $event
            ->setVisible(1)
            ->setLocale($brand->getLocale())
            ->setTitle($brand->getTitle())
            ->setChapo($brand->getChapo())
            ->setDescription($brand->getDescription())
            ->setPostscriptum($brand->getPostscriptum())
        ;

        return $event;
    }

    public function processUpdateAction($event)
    {
        $brandAction = new Brand($this->getContainer());
        $brandAction->update($event);

        return $event->getBrand();
    }

    public function testCreateBrand()
    {
        $event = new BrandCreateEvent();
        $event->setDispatcher($this->dispatcher);
        $event
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('test create brand')
        ;

        $brandAction = new Brand($this->getContainer());
        $brandAction->create($event);

        $createdBrand = $event->getBrand();

        $this->assertInstanceOf('Thelia\Model\Brand', $createdBrand);
        $this->assertEquals(1, $createdBrand->getVisible());
        $this->assertEquals('test create brand', $createdBrand->getTitle());
    }

    public function testUpdateBrand()
    {
        $brand = $this->getRandomBrand();

        $event = new BrandUpdateEvent($brand->getId());
        $event->setDispatcher($this->dispatcher);
        $event
            ->setVisible(1)
            ->setLocale('en_US')
            ->setTitle('test update brand title')
            ->setChapo('test update brand short description')
            ->setDescription('test update brand description')
            ->setPostscriptum('test update brand postscriptum')
        ;

        $brandAction = new Brand($this->getContainer());
        $brandAction->update($event);

        $updatedBrand = $event->getBrand();

        $this->assertInstanceOf('Thelia\Model\Brand', $updatedBrand);
        $this->assertEquals(1, $updatedBrand->getVisible());
        $this->assertEquals('test update brand title', $updatedBrand->getTitle());
        $this->assertEquals('test update brand short description', $updatedBrand->getChapo());
        $this->assertEquals('test update brand description', $updatedBrand->getDescription());
        $this->assertEquals('test update brand postscriptum', $updatedBrand->getPostscriptum());
    }

    public function testDeleteBrand()
    {
        $brand = $this->getRandomBrand();

        $event = new BrandDeleteEvent($brand->getId());
        $event->setDispatcher($this->dispatcher);

        $brandAction = new Brand($this->getContainer());
        $brandAction->delete($event);

        $deletedBrand = $event->getBrand();

        $this->assertInstanceOf('Thelia\Model\Brand', $deletedBrand);
        $this->assertTrue($deletedBrand->isDeleted());
    }

    public function testBrandToggleVisibility()
    {
        $brand = $this->getRandomBrand();

        $visibility = $brand->getVisible();

        $event = new BrandToggleVisibilityEvent($brand);
        $event->setDispatcher($this->dispatcher);

        $brandAction = new Brand($this->getContainer());
        $brandAction->toggleVisibility($event);

        $updatedBrand = $event->getBrand();

        $this->assertInstanceOf('Thelia\Model\Brand', $updatedBrand);
        $this->assertEquals(!$visibility, $updatedBrand->getVisible());
    }

    public function testUpdatePositionUp()
    {
        $this->resetBrandPosition();

        $brand = BrandQuery::create()
            ->filterByPosition(1, Criteria::GREATER_THAN)
            ->findOne();

        if (null === $brand) {
            $this->fail('use fixtures before launching test, there is no brand in database');
        }

        $newPosition = $brand->getPosition()-1;

        $event = new UpdatePositionEvent($brand->getId(), UpdatePositionEvent::POSITION_UP);
        $event->setDispatcher($this->dispatcher);

        $brandAction = new Brand($this->getContainer());
        $brandAction->updatePosition($event);

        $updatedBrand = BrandQuery::create()->findPk($brand->getId());

        $this->assertEquals($newPosition, $updatedBrand->getPosition(), sprintf("new position is %d, new position expected is %d for brand %d", $newPosition, $updatedBrand->getPosition(), $updatedBrand->getId()));
    }

    public function testUpdatePositionDown()
    {
        $this->resetBrandPosition();

        $brand = BrandQuery::create()
            ->filterByPosition(1)
            ->findOne();

        if (null === $brand) {
            $this->fail('use fixtures before launching test, there is no brand in database');
        }

        $newPosition = $brand->getPosition()+1;

        $event = new UpdatePositionEvent($brand->getId(), UpdatePositionEvent::POSITION_DOWN);
        $event->setDispatcher($this->dispatcher);

        $brandAction = new Brand($this->getContainer());
        $brandAction->updatePosition($event);

        $updatedBrand = BrandQuery::create()->findPk($brand->getId());

        $this->assertEquals($newPosition, $updatedBrand->getPosition(),sprintf("new position is %d, new position expected is %d for brand %d", $newPosition, $updatedBrand->getPosition(), $updatedBrand->getId()));
    }

    public function testUpdatePositionWithSpecificPosition()
    {
        $this->resetBrandPosition();

        $brand = BrandQuery::create()
            ->filterByPosition(1, Criteria::GREATER_THAN)
            ->findOne();

        if (null === $brand) {
            $this->fail('use fixtures before launching test, there is no brand in database');
        }

        $event = new UpdatePositionEvent($brand->getId(), UpdatePositionEvent::POSITION_ABSOLUTE, 1);
        $event->setDispatcher($this->dispatcher);

        $brandAction = new Brand($this->getContainer());
        $brandAction->updatePosition($event);

        $updatedBrand = BrandQuery::create()->findPk($brand->getId());

        $this->assertEquals(1, $updatedBrand->getPosition(),sprintf("new position is 1, new position expected is %d for brand %d", $updatedBrand->getPosition(), $updatedBrand->getId()));
    }

    /**
     * Reorder brand to have proper position
     */
    protected function resetBrandPosition()
    {
        $brands = BrandQuery::create()->find();
        $counter = 1;

        /** @var \Thelia\Model\Brand $brand */
        foreach ($brands as $brand) {
            $brand->setPosition($counter);
            $brand->save();
            $counter++;
        }
    }

    /**
     * @return \Thelia\Model\Brand
     */
    protected function getRandomBrand()
    {
        $brand = BrandQuery::create()
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        if (null === $brand) {
            $this->fail('use fixtures before launching test, there is no brand in database');
        }

        return $brand;
    }
}
