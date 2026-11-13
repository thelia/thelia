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

namespace Thelia\Domain\Subscription\Message;

use Thelia\Messenger\Message\DescribedJob;

/**
 * Bill what is due on a subscription. Carries the id alone: the handler reads the
 * subscription again under its lock, so a message delivered twice or late does no harm.
 */
final readonly class BillSubscriptionInstallment implements DescribedJob
{
    public function __construct(public int $subscriptionId)
    {
    }

    public function describe(): string
    {
        return \sprintf('Subscription billing #%d', $this->subscriptionId);
    }
}
