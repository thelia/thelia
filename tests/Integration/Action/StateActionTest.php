<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Integration\Action;

use Thelia\Core\Event\State\StateCreateEvent;
use Thelia\Core\Event\State\StateDeleteEvent;
use Thelia\Core\Event\State\StateToggleVisibilityEvent;
use Thelia\Core\Event\State\StateUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\StateQuery;
use Thelia\Test\ActionIntegrationTestCase;

final class StateActionTest extends ActionIntegrationTestCase
{
    public function testCreatePersistsStateForCountry(): void
    {
        $country = $this->factory->country();

        $event = new StateCreateEvent();
        $event
            ->setCountry($country->getId())
            ->setIsocode('IDF')
            ->setLocale('en_US')
            ->setTitle('Ile-de-France')
            ->setVisible(true);

        $this->dispatch($event, TheliaEvents::STATE_CREATE);

        $state = $event->getState();
        self::assertNotNull($state);
        self::assertSame($country->getId(), $state->getCountryId());
        self::assertSame('IDF', $state->getIsocode());
        self::assertSame('Ile-de-France', $state->setLocale('en_US')->getTitle());
    }

    public function testUpdateChangesTitleAndIsoCode(): void
    {
        $country = $this->factory->country();
        $state = $this->dispatch(
            (new StateCreateEvent())
                ->setCountry($country->getId())
                ->setIsocode('OLD')
                ->setLocale('en_US')
                ->setTitle('Old Name')
                ->setVisible(true),
            TheliaEvents::STATE_CREATE,
        )->getState();

        $event = new StateUpdateEvent($state->getId());
        $event
            ->setCountry($country->getId())
            ->setIsocode('NEW')
            ->setLocale('en_US')
            ->setTitle('New Name')
            ->setVisible(true);

        $this->dispatch($event, TheliaEvents::STATE_UPDATE);

        $reloaded = StateQuery::create()->findPk($state->getId());
        self::assertSame('NEW', $reloaded->getIsocode());
        self::assertSame('New Name', $reloaded->setLocale('en_US')->getTitle());
    }

    public function testToggleVisibilityFlipsFlag(): void
    {
        $country = $this->factory->country();
        $state = $this->dispatch(
            (new StateCreateEvent())
                ->setCountry($country->getId())
                ->setIsocode('TG1')
                ->setLocale('en_US')
                ->setTitle('Togglable')
                ->setVisible(true),
            TheliaEvents::STATE_CREATE,
        )->getState();

        $event = new StateToggleVisibilityEvent();
        $event->setState($state);
        $this->dispatch($event, TheliaEvents::STATE_TOGGLE_VISIBILITY);

        self::assertSame(0, (int) StateQuery::create()->findPk($state->getId())->getVisible());
    }

    public function testDeleteRemovesState(): void
    {
        $country = $this->factory->country();
        $state = $this->dispatch(
            (new StateCreateEvent())
                ->setCountry($country->getId())
                ->setIsocode('DEL')
                ->setLocale('en_US')
                ->setTitle('Disposable')
                ->setVisible(true),
            TheliaEvents::STATE_CREATE,
        )->getState();
        $stateId = $state->getId();

        $this->dispatch(new StateDeleteEvent($stateId), TheliaEvents::STATE_DELETE);

        self::assertNull(StateQuery::create()->findPk($stateId));
    }
}
