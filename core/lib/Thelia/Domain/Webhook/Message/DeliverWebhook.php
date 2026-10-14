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

namespace Thelia\Domain\Webhook\Message;

use Thelia\Messenger\Message\DescribedJob;

final readonly class DeliverWebhook implements DescribedJob
{
    public function __construct(public int $deliveryId)
    {
    }

    public function describe(): string
    {
        return \sprintf('Webhook delivery #%d', $this->deliveryId);
    }
}
