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

namespace Thelia\Api\Bridge\Propel\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\EventListener\EffectivePriceListener;
use Thelia\Api\Resource\Product;
use Thelia\Api\Resource\ProductCategory;
use Thelia\Domain\Catalog\Category\CategoryVisibility;

/**
 * Keeps a category closed to the customer group of the reader out of the
 * `productCategories` a product embeds on the front: a product an open category keeps
 * visible must not give away the closed one it also sits in. The admin reads are left
 * whole.
 *
 * Done on the resource once built, because the relation is an array the serializer
 * reads as it is: no query of the product can narrow it.
 */
final readonly class ProductCategoryVisibilityListener implements EventSubscriberInterface
{
    public function __construct(private CategoryVisibility $categoryVisibility)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [['hideClosedCategories', 0]],
        ];
    }

    public function hideClosedCategories(ModelToResourceEvent $event): void
    {
        $product = $event->getResource();

        if (!$product instanceof Product || !EffectivePriceListener::isFrontRead($event->getContext()) || [] === $product->productCategories) {
            return;
        }

        $closedCategoryIds = $this->categoryVisibility->closedCategoryIds();

        if ([] === $closedCategoryIds) {
            return;
        }

        $product->setProductCategories(array_values(array_filter(
            $product->productCategories,
            static fn (mixed $productCategory): bool => !$productCategory instanceof ProductCategory
                || !isset($productCategory->category)
                || !\in_array($productCategory->category->getId(), $closedCategoryIds, true),
        )));
    }
}
