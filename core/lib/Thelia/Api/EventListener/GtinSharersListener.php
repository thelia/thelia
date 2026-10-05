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
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Api\Bridge\Propel\Event\CollectionModelsLoadedEvent;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\ProductSaleElements as ProductSaleElementsResource;
use Thelia\Domain\Catalog\Product\Identifier\GtinDuplicateFinder;
use Thelia\Domain\Catalog\Product\Identifier\GtinSharer;
use Thelia\Model\ProductSaleElements;

/**
 * Tells an administrator reading a combination which other combinations carry its GTIN,
 * the warning the back office shows. A front read never asks: the id of another
 * combination is none of the visitor's business.
 *
 * Only a combination that has a code costs a lookup, on the indexed column. A page of
 * combinations is looked up once, before its members are transformed, and each member
 * is then answered from memory.
 */
class GtinSharersListener implements EventSubscriberInterface, ResetInterface
{
    /** @var array<int, list<int>> sharers of the coded combinations of the page being transformed, by combination id */
    private array $sharersOfThePage = [];

    public function __construct(private readonly GtinDuplicateFinder $duplicateFinder)
    {
    }

    public function warm(CollectionModelsLoadedEvent $event): void
    {
        $this->sharersOfThePage = [];

        if (!self::readsTheCombinationAsAdmin($event->getContext())) {
            return;
        }

        $codedIds = [];

        foreach ($event->getModels() as $model) {
            if ($model instanceof ProductSaleElements && null !== $model->getId() && '' !== (string) $model->getEanCode()) {
                $codedIds[] = (int) $model->getId();
            }
        }

        if ([] === $codedIds) {
            return;
        }

        $sharersById = $this->duplicateFinder->sharersAmong($codedIds);

        foreach ($codedIds as $id) {
            $this->sharersOfThePage[$id] = self::idsOf($sharersById[$id] ?? []);
        }
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

        $id = $resource->getId();

        // Answered once from the page: a later read of the same combination looks again.
        if (\array_key_exists($id, $this->sharersOfThePage)) {
            $resource->setGtinSharedWith($this->sharersOfThePage[$id]);
            unset($this->sharersOfThePage[$id]);

            return;
        }

        $resource->setGtinSharedWith(self::idsOf($this->duplicateFinder->sharersOf($id)));
    }

    public function reset(): void
    {
        $this->sharersOfThePage = [];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CollectionModelsLoadedEvent::class => 'warm',
            ModelToResourceEvent::AFTER_TRANSFORM => [
                ['addSharers', 0],
            ],
        ];
    }

    /**
     * @param list<GtinSharer> $sharers
     *
     * @return list<int>
     */
    private static function idsOf(array $sharers): array
    {
        return array_map(static fn (GtinSharer $sharer): int => $sharer->productSaleElementsId, $sharers);
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
