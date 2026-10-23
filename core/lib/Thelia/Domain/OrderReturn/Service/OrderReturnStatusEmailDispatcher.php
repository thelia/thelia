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

namespace Thelia\Domain\OrderReturn\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderReturn;

/**
 * The one place that tells the customer a return changed status, by
 * dispatching ORDER_RETURN_SEND_STATUS_EMAIL.
 *
 * Call it once the write that changed the return is committed, never from
 * inside its transaction: a mail is not something a rollback takes back. It
 * is called by the listener that follows ORDER_RETURN_CREATE,
 * ORDER_RETURN_RECEIVE and ORDER_RETURN_UPDATE_STATUS, by the API processors
 * that open a return, and by a theme that writes a return its own way. A
 * caller that dispatches one of those three events must not call it as well:
 * the listener already has, and the customer would be told twice.
 */
final readonly class OrderReturnStatusEmailDispatcher
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function dispatchFor(OrderReturn $orderReturn): void
    {
        $this->dispatchEvent(new OrderReturnEvent($orderReturn));
    }

    /**
     * Announces the return of an event already in flight, so a listener of
     * the mail still reads the refusal reason or the target status it carried.
     */
    public function dispatchEvent(OrderReturnEvent $event): void
    {
        $this->eventDispatcher->dispatch($event, TheliaEvents::ORDER_RETURN_SEND_STATUS_EMAIL);
    }
}
