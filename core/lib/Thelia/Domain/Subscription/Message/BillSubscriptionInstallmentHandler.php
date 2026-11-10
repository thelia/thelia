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

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Thelia\Domain\Subscription\SubscriptionInstallmentBiller;

#[AsMessageHandler]
final readonly class BillSubscriptionInstallmentHandler
{
    public function __construct(
        private SubscriptionInstallmentBiller $biller,
    ) {
    }

    public function __invoke(BillSubscriptionInstallment $message): void
    {
        $this->biller->bill($message->subscriptionId);
    }
}
