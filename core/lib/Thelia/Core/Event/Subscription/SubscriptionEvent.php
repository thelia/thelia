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

namespace Thelia\Core\Event\Subscription;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Subscription;

/**
 * Tells that a subscription was created (TheliaEvents::SUBSCRIPTION_CREATE), once it is
 * committed. Sent to announce, never to perform: a listener that fails does not undo it.
 */
class SubscriptionEvent extends ActionEvent
{
    public function __construct(private Subscription $subscription)
    {
    }

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }
}
