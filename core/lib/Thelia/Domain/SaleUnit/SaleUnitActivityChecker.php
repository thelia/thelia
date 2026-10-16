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

namespace Thelia\Domain\SaleUnit;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Model\SaleUnitQuery;

/**
 * Whether the shop sells from sale units at all: the multi-sale-unit mode is on as
 * soon as one unit is active, and off for a single shop, which must keep the queries
 * it had before the feature.
 *
 * The answer is kept in the pool the shop configuration lives in, shared by every
 * process the same way, without a lifetime: a single shop never asks the database
 * again once the entry is written. Whatever writes or deletes a sale unit drops the
 * entry (SaleUnitCacheListener), and a cache clear wipes it with the container cache,
 * the safety net for a row changed outside Propel.
 *
 * A request that changed a sale unit reads the database for its own remainder and
 * publishes nothing, so a value its transaction may still roll back never reaches
 * the others; the entry is dropped once more when that request ends, after its
 * commit, in case another process wrote it in between.
 */
class SaleUnitActivityChecker implements ResetInterface
{
    public const CACHE_KEY = 'thelia_sale_unit_active';

    private ?bool $hasActiveSaleUnit = null;

    private bool $changedDuringTheRequest = false;

    public function __construct(
        #[Autowire(service: 'thelia.cache.config.adapter')]
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function isMultiSaleUnitMode(): bool
    {
        if (null !== $this->hasActiveSaleUnit) {
            return $this->hasActiveSaleUnit;
        }

        if (null === $this->cache || $this->changedDuringTheRequest) {
            return $this->hasActiveSaleUnit = self::anySaleUnitIsActive();
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        $cached = $item->isHit() ? $item->get() : null;

        if (\is_bool($cached)) {
            return $this->hasActiveSaleUnit = $cached;
        }

        $this->hasActiveSaleUnit = self::anySaleUnitIsActive();
        $this->cache->save($item->set($this->hasActiveSaleUnit));

        return $this->hasActiveSaleUnit;
    }

    /**
     * A sale unit was written or deleted: the shared answer may be wrong from now on.
     */
    public function forget(): void
    {
        $this->cache?->deleteItem(self::CACHE_KEY);
        $this->hasActiveSaleUnit = null;
        $this->changedDuringTheRequest = true;
    }

    /**
     * The request that changed a sale unit is over and its transaction settled: the
     * entry another process may have written meanwhile is dropped too.
     */
    public function forgetAfterTheRequest(): void
    {
        if (!$this->changedDuringTheRequest) {
            return;
        }

        $this->cache?->deleteItem(self::CACHE_KEY);
        $this->changedDuringTheRequest = false;
    }

    public function reset(): void
    {
        $this->hasActiveSaleUnit = null;
    }

    private static function anySaleUnitIsActive(): bool
    {
        return SaleUnitQuery::create()
            ->filterByActive(true)
            ->exists();
    }
}
