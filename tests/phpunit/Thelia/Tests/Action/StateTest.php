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

use Thelia\Action\State;
use Thelia\Core\Event\State\StateCreateEvent;
use Thelia\Core\Event\State\StateDeleteEvent;
use Thelia\Core\Event\State\StateUpdateEvent;
use Thelia\Model\CountryQuery;
use Thelia\Model\State as StateModel;

/**
 * Class StateTest
 * @package Thelia\Tests\Action
 * @author Julien Chanséaume <julien@thelia.net>
 */
class StateTest extends BaseAction
{
    public function testCreate()
    {
        $country = CountryQuery::create()
            ->filterByHasStates(1)
            ->findOne()
        ;

        $event = new StateCreateEvent();

        $event
            ->setVisible(1)
            ->setCountry($country->getId())
            ->setIsocode('AA')
            ->setLocale('en_US')
            ->setTitle('State1')
        ;

        $action = new State();
        $action->create($event);

        $createdState = $event->getState();

        $this->assertInstanceOf('Thelia\Model\State', $createdState);
        $this->assertFalse($createdState->isNew());

        $this->assertEquals($country->getId(), $createdState->getCountryId());
        $this->assertEquals('AA', $createdState->getIsocode());
        $this->assertEquals('en_US', $createdState->getLocale());
        $this->assertEquals('State1', $createdState->getTitle());

        return $createdState;
    }

    /**
     * @param StateModel $state
     * @depends testCreate
     * @return StateModel
     */
    public function testUpdate(StateModel $state)
    {
        $event = new StateUpdateEvent($state->getId());

        $event
            ->setIsocode('BB')
            ->setVisible(0)
            ->setCountry($state->getCountryId())
            ->setLocale('en_US')
            ->setTitle('State2')
        ;

        $action = new State();
        $action->update($event);

        $updatedState = $event->getState();

        $this->assertInstanceOf('Thelia\Model\State', $updatedState);

        $this->assertEquals('BB', $updatedState->getIsocode());
        $this->assertEquals(0, $updatedState->getVisible());
        $this->assertEquals('en_US', $updatedState->getLocale());
        $this->assertEquals('State2', $updatedState->getTitle());

        return $updatedState;
    }

    /**
     * @param StateModel $state
     * @depends testUpdate
     */
    public function testDelete(StateModel $state)
    {
        $event = new StateDeleteEvent($state->getId());

        $action = new State();
        $action->delete($event);

        $deletedState = $event->getState();

        $this->assertInstanceOf('Thelia\Model\State', $deletedState);
        $this->assertTrue($deletedState->isDeleted());
    }
}
