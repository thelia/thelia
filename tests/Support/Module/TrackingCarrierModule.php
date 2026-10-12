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

namespace Thelia\Tests\Support\Module;

use Thelia\Model\Country;
use Thelia\Model\Order;
use Thelia\Model\State;
use Thelia\Module\AbstractDeliveryModuleWithState;
use Thelia\Module\DeliveryTrackingUrlProviderInterface;

/**
 * A delivery module whose carrier signs its tracking links, so it builds them itself
 * instead of relying on a template typed by the merchant.
 */
final class TrackingCarrierModule extends AbstractDeliveryModuleWithState implements DeliveryTrackingUrlProviderInterface
{
    public function __construct(
        private readonly string $code,
        private readonly ?string $trackingUrl,
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isValidDelivery(Country $country, ?State $state = null): bool
    {
        return true;
    }

    public function getPostage(Country $country, ?State $state = null): float
    {
        return 0.0;
    }

    public function getTrackingUrl(Order $order): ?string
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        return null === $this->trackingUrl ? null : str_replace('{ref}', (string) $order->getDeliveryRef(), $this->trackingUrl);
    }
}
