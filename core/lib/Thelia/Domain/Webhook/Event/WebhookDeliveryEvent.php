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

namespace Thelia\Domain\Webhook\Event;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\WebhookDelivery;

class WebhookDeliveryEvent extends ActionEvent
{
    private ?WebhookDelivery $delivery = null;

    public function getDelivery(): ?WebhookDelivery
    {
        return $this->delivery;
    }

    public function setDelivery(?WebhookDelivery $delivery): static
    {
        $this->delivery = $delivery;

        return $this;
    }
}
