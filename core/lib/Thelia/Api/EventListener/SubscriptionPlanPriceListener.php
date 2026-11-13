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

namespace Thelia\Api\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\SubscriptionPlan as SubscriptionPlanResource;
use Thelia\Domain\Catalog\Product\VisitorPriceReader;
use Thelia\Domain\Pricing\PriceVisibility\PriceVisibilityResolver;
use Thelia\Domain\Subscription\Exception\SubscriptionPlanPriceMissingException;
use Thelia\Domain\Subscription\SubscriptionPlanPricing;
use Thelia\Model\SubscriptionPlan;

/**
 * Gives a plan read by the front the price of one installment in the currency being
 * browsed (the one of the product prices), untaxed. A plan with no price in that currency
 * nor in the default one is left without a price: it cannot be bought. Nor is a plan whose
 * product shows no price to this visitor (prices for signed-in customers, prices on request):
 * the plan follows the price display mode of its product.
 */
final readonly class SubscriptionPlanPriceListener implements EventSubscriberInterface
{
    public function __construct(
        private SubscriptionPlanPricing $pricing,
        private ProductPriceCurrencyListener $currencyListener,
        private PriceVisibilityResolver $priceVisibilityResolver,
        private VisitorPriceReader $visitorPriceReader,
    ) {
    }

    public function addThePrice(ModelToResourceEvent $event): void
    {
        $resource = $event->getResource();
        $model = $event->getModel();
        $groups = $event->getContext()['groups'] ?? [];
        $groups = \is_array($groups) ? $groups : [$groups];

        if (!$resource instanceof SubscriptionPlanResource || !$model instanceof SubscriptionPlan || !\in_array(SubscriptionPlanResource::GROUP_FRONT_READ, $groups, true)) {
            return;
        }

        if ($this->priceVisibilityResolver->isActive()
            && !$this->priceVisibilityResolver->showsPriceOf((int) $model->getProductId(), $this->visitorPriceReader->currentCustomer())) {
            $resource->price = null;

            return;
        }

        $currency = $this->currencyListener->currentCurrency();

        try {
            $resource->price = (float) $this->pricing->priceOf($model, $currency);
            $resource->currencyCode = $currency->getCode();
        } catch (SubscriptionPlanPriceMissingException) {
            $resource->price = null;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['addThePrice', 0],
            ],
        ];
    }
}
