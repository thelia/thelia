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
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\AbstractDeliveryModuleWithState;

/**
 * A carrier that prices by weight the way published modules do (CustomDelivery among
 * them): it reads the cart of the session, not the one carried by the postage event,
 * and taxes its postage under a rule of its own.
 *
 * 5.00 untaxed, plus 1.00 per kilo.
 */
final class SessionCartCarrierModule extends AbstractDeliveryModuleWithState
{
    /** @var list<array{weight: float, stateId: ?int}> what the carrier saw each time it was asked */
    public array $seen = [];

    public function __construct(
        private readonly string $code,
        private readonly int $taxRuleId,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isValidDelivery(Country $country, ?State $state = null): bool
    {
        return $this->sessionCartWeight() > 0;
    }

    public function getPostage(Country $country, ?State $state = null): OrderPostage
    {
        $weight = $this->sessionCartWeight();
        $this->seen[] = ['weight' => $weight, 'stateId' => $state?->getId()];

        return $this->buildOrderPostage(5.0 + $weight, $country, 'en_US', $this->taxRuleId);
    }

    private function sessionCartWeight(): float
    {
        return (float) $this->getRequest()->getSession()->getSessionCart($this->getDispatcher())->getWeight();
    }
}
