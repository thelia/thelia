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
use Thelia\Api\Bridge\Propel\Event\ResourcePersistedEvent;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Pricing\Rule\RuleRepricer;
use Thelia\Model\AttributeCombination;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Product;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductSaleElements;

/**
 * Keeps the catalog price rules in step with the catalog writes made through the
 * API, which never reach the Thelia\Action\* listeners the back office goes through.
 *
 * Whatever the resource, the question is the same: which product changed. Its scope
 * membership and its stored prices are then re-evaluated exactly as after a
 * back-office edit.
 */
class CatalogPriceRuleResourceSyncListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RuleRepricer $repricer,
        private readonly PricingActivityChecker $activityChecker,
    ) {
    }

    public function afterPersist(ResourcePersistedEvent $event): void
    {
        $productId = $this->productIdOf($event);

        if (null === $productId) {
            return;
        }

        if (!$this->activityChecker->hasActivePublicRule() && !$this->activityChecker->hasActiveNamedRule()) {
            return;
        }

        $this->repricer->afterProductChanged($productId);
    }

    public static function getSubscribedEvents(): array
    {
        return [ResourcePersistedEvent::class => 'afterPersist'];
    }

    private function productIdOf(ResourcePersistedEvent $event): ?int
    {
        $model = $event->getModel();

        // A deleted product or sale element takes its scope rows and stored prices
        // with it by cascade; nothing is left to re-evaluate.
        if ($event->isDelete() && ($model instanceof Product || $model instanceof ProductSaleElements)) {
            return null;
        }

        return match (true) {
            $model instanceof Product => (int) $model->getId(),
            $model instanceof ProductSaleElements, $model instanceof ProductCategory, $model instanceof FeatureProduct => (int) $model->getProductId(),
            $model instanceof ProductPrice, $model instanceof AttributeCombination => $this->productIdOfSaleElement((int) $model->getProductSaleElementsId()),
            default => null,
        };
    }

    private function productIdOfSaleElement(int $productSaleElementsId): ?int
    {
        $productId = \Thelia\Model\ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsId)
            ->select('ProductId')
            ->findOne();

        return null === $productId ? null : (int) $productId;
    }
}
