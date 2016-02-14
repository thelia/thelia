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

use Thelia\Action\Country;
use Thelia\Core\Event\Country\CountryCreateEvent;
use Thelia\Core\Event\Country\CountryDeleteEvent;
use Thelia\Core\Event\Country\CountryToggleDefaultEvent;
use Thelia\Core\Event\Country\CountryUpdateEvent;
use Thelia\Model\Country as CountryModel;
use Thelia\Model\CountryQuery;

/**
 * Class CountryTest
 * @package Thelia\Tests\Action
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryTest extends BaseAction
{
    public function testCreate()
    {
        $event = new CountryCreateEvent();

        $event
            ->setIsocode('001')
            ->setIsoAlpha2('AA')
            ->setIsoAlpha3('AAA')
            ->setVisible(1)
            ->setHasStates(0)
            ->setLocale('en_US')
            ->setTitle('Test')
        ;

        $action = new Country();
        $action->create($event, null, $this->getMockEventDispatcher());

        $createdCountry = $event->getCountry();

        $this->assertInstanceOf('Thelia\Model\Country', $createdCountry);
        $this->assertFalse($createdCountry->isNew());

        $this->assertEquals('001', $createdCountry->getIsocode());
        $this->assertEquals('AA', $createdCountry->getIsoalpha2());
        $this->assertEquals('AAA', $createdCountry->getIsoalpha3());
        $this->assertEquals(1, $createdCountry->getVisible());
        $this->assertEquals(0, $createdCountry->getHasStates());
        $this->assertEquals('AAA', $createdCountry->getIsoalpha3());
        $this->assertEquals('en_US', $createdCountry->getLocale());
        $this->assertEquals('Test', $createdCountry->getTitle());

        return $createdCountry;
    }

    /**
     * @param CountryModel $country
     * @depends testCreate
     * @return CountryModel
     */
    public function testUpdate(CountryModel $country)
    {
        $event = new CountryUpdateEvent($country->getId());

        $event
            ->setIsocode('002')
            ->setIsoAlpha2('BB')
            ->setIsoAlpha3('BBB')
            ->setVisible(1)
            ->setHasStates(0)
            ->setLocale('en_US')
            ->setTitle('Test')
        ;

        $action = new Country();
        $action->update($event, null, $this->getMockEventDispatcher());

        $updatedCountry = $event->getCountry();

        $this->assertInstanceOf('Thelia\Model\Country', $updatedCountry);

        $this->assertEquals('002', $updatedCountry->getIsocode());
        $this->assertEquals('BB', $updatedCountry->getIsoalpha2());
        $this->assertEquals('BBB', $updatedCountry->getIsoalpha3());
        $this->assertEquals(1, $updatedCountry->getVisible());
        $this->assertEquals(0, $updatedCountry->getHasStates());
        $this->assertEquals('en_US', $updatedCountry->getLocale());
        $this->assertEquals('Test', $updatedCountry->getTitle());

        return $updatedCountry;
    }

    /**
     * @param CountryModel $country
     * @depends testUpdate
     */
    public function testDelete(CountryModel $country)
    {
        $event = new CountryDeleteEvent($country->getId());

        $action = new Country();
        $action->delete($event, null, $this->getMockEventDispatcher());

        $deletedCountry = $event->getCountry();

        $this->assertInstanceOf('Thelia\Model\Country', $deletedCountry);
        $this->assertTrue($deletedCountry->isDeleted());
    }

    public function testToggleDefault()
    {
        /** @var CountryModel $country */
        $country = CountryQuery::create()
            ->filterByByDefault(0)
            ->addAscendingOrderByColumn('RAND()')
            ->findOne();

        $event = new CountryToggleDefaultEvent($country->getId());

        $action = new Country($this->getMockEventDispatcher());
        $action->toggleDefault($event, null, $this->getMockEventDispatcher());

        $updatedCountry = $event->getCountry();

        $this->assertInstanceOf('Thelia\Model\Country', $updatedCountry);
        $this->assertEquals(1, $updatedCountry->getByDefault());

        $this->assertEquals(1, CountryQuery::create()->filterByByDefault(1)->count());
    }
}
