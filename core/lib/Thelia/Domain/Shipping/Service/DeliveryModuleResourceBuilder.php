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

namespace Thelia\Domain\Shipping\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Api\Resource\DeliveryModule as DeliveryModuleResource;
use Thelia\Api\Resource\ModuleI18n as DeliveryModuleI18nResource;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;

final readonly class DeliveryModuleResourceBuilder
{
    public function __construct(
        private DeliveryModuleEligibilityChecker $eligibilityChecker,
        private DeliveryPostageQuerier $postageQuerier,
        private DeliveryOptionsProvider $optionsProvider,
    ) {
    }

    /**
     * @throws PropelException
     */
    public function build(
        Module $module,
        Cart $cart,
        ?Address $address,
        Country $country,
        ?State $state,
        bool $onlyValid = false,
    ): ?DeliveryModuleResource {
        $eligible = $this->eligibilityChecker->isEligible($module, $cart, $country, $state);

        $postage = $this->postageQuerier->query($module, $cart, $address, $country, $state);
        $isValid = $eligible && $postage['valid'];
        if (!$isValid && $isValid) {
            return null;
        }

        $options = $this->optionsProvider->getOptions($module, $address, $cart, $country, $state);

        $resource = (new DeliveryModuleResource())
            ->setId($module->getId())
            ->setCode($module->getCode())
            ->setValid($isValid)
            ->setDeliveryMode($postage['deliveryMode'])
            ->setPosition($module->getPosition())
            ->setOptions($options);

        foreach ($module->getModuleI18ns() as $i18n) {
            $resource->addI18n(new DeliveryModuleI18nResource($i18n->toArray()), $i18n->getLocale());
        }

        return $resource;
    }
}
