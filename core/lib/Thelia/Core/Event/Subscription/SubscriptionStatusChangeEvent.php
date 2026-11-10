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

use Thelia\Domain\Subscription\Enum\ActorType;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Model\Subscription;

/**
 * Tells that a subscription changed status (TheliaEvents::SUBSCRIPTION_STATUS_CHANGE),
 * once the change is committed, with who made it. The subscription carries the new
 * status and the dates written with it.
 */
final class SubscriptionStatusChangeEvent extends SubscriptionEvent
{
    public function __construct(
        Subscription $subscription,
        private readonly SubscriptionStatus $previousStatus,
        private readonly SubscriptionStatus $newStatus,
        private readonly ActorType $actorType,
        private readonly ?int $actorId = null,
        private readonly ?string $note = null,
    ) {
        parent::__construct($subscription);
    }

    public function getPreviousStatus(): SubscriptionStatus
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): SubscriptionStatus
    {
        return $this->newStatus;
    }

    public function getActorType(): ActorType
    {
        return $this->actorType;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }
}
