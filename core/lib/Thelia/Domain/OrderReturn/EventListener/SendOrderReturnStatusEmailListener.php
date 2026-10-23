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

namespace Thelia\Domain\OrderReturn\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Service\OrderReturnStatusEmailDispatcher;

/**
 * Announces a return gesture to the customer, once everything that records it
 * has run.
 *
 * Thelia\Action\OrderReturn (128) has committed its own transaction by then,
 * and RecordOrderReturnHistoryListener (64) has written the line of the gesture
 * on the order: the timeline shows what the mail announces before the mail.
 * Nothing is sent when the action throws, since the listeners below it never run.
 *
 * It is the only sender of the three gestures. The action no longer dispatches
 * ORDER_RETURN_SEND_STATUS_EMAIL itself, so a direct call to create(),
 * receive() or updateStatus() sends nothing: only the dispatch of the event
 * does. A listener between 32 and 128 that throws or stops the propagation
 * keeps the mail from going.
 */
final readonly class SendOrderReturnStatusEmailListener
{
    public function __construct(
        private OrderReturnStatusEmailDispatcher $statusEmailDispatcher,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_CREATE, priority: 32)]
    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_RECEIVE, priority: 32)]
    #[AsEventListener(event: TheliaEvents::ORDER_RETURN_UPDATE_STATUS, priority: 32)]
    public function __invoke(OrderReturnEvent $event): void
    {
        $this->statusEmailDispatcher->dispatchEvent($event);
    }
}
