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
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Catalog\Product\Identifier\GtinDuplicateFinder;
use Thelia\Domain\Catalog\Product\Identifier\GtinSharer;

/**
 * Tells an administrator reading a combination which other combinations carry its GTIN,
 * the warning the back office shows. A front read never asks: the id of another
 * combination is none of the visitor's business.
 *
 * Only a combination that has a code costs a lookup, on the indexed column.
 */
class GtinSharersListener implements EventSubscriberInterface
{
    public function __construct(private readonly GtinDuplicateFinder $duplicateFinder)
    {
    }

    public function addSharers(ModelToResourceEvent $modelToResourceEvent): void
    {
        $resource = $modelToResourceEvent->getResource();

        if (!$resource instanceof ProductSaleElementsResource
            || null === $resource->getId()
            || null === $resource->getEanCode()
            || '' === $resource->getEanCode()
            || !self::readsTheCombinationAsAdmin($modelToResourceEvent->getContext())) {
            return;
        }

        $resource->setGtinSharedWith(array_map(
            static fn (GtinSharer $sharer): int => $sharer->productSaleElementsId,
            $this->duplicateFinder->sharersOf($resource->getId()),
        ));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['addSharers', 0],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function readsTheCombinationAsAdmin(array $context): bool
    {
        $groups = $context['groups'] ?? [];

        return \in_array(ProductSaleElementsResource::GROUP_ADMIN_READ, \is_array($groups) ? $groups : [$groups], true);
    }
}
