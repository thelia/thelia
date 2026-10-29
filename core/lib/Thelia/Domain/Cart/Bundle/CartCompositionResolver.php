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

namespace Thelia\Domain\Cart\Bundle;

use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Catalog\Bundle\BundleCatalogState;
use Thelia\Domain\Catalog\Bundle\BundleComposition;
use Thelia\Domain\Catalog\Bundle\BundleCompositionResolver;
use Thelia\Domain\Catalog\Bundle\BundleItemRepository;
use Thelia\Domain\Catalog\Bundle\Exception\InvalidBundleCompositionException;

/**
 * The composition a cart line will hold, for the two callers that name it before the cart
 * does: the API, which needs it to find the line the addition joins, and the guard of the
 * addition (BundleCartAdditionGuard), which judges every addition whoever sends it.
 *
 * A POST of a bundle is resolved once: what the API resolved is handed to the guard, once,
 * instead of being resolved again. A shop without a bundle answers without a query.
 */
final class CartCompositionResolver implements ResetInterface
{
    /** @var array<string, BundleComposition|null> by product and composition; null for a product that is not a bundle */
    private array $handedOver = [];

    public function __construct(
        private readonly BundleCatalogState $bundleCatalogState,
        private readonly BundleItemRepository $bundleItemRepository,
        private readonly BundleCompositionResolver $bundleCompositionResolver,
    ) {
    }

    /**
     * Resolves what a caller proposes, and keeps the answer for the guard of the addition.
     *
     * @param BundleComposition|null $proposed null for the default composition, or for a product that is not a bundle
     *
     * @return BundleComposition|null null for a product that is not a bundle
     *
     * @throws InvalidBundleCompositionException the composition cannot be held, or none is expected from the product
     */
    public function resolveAndHandOver(int $productId, ?BundleComposition $proposed): ?BundleComposition
    {
        $composition = $this->resolve($productId, $proposed);
        $this->handedOver[self::keyOf($productId, $composition)] = $composition;

        return $composition;
    }

    /**
     * What the guard judges: the composition of the event, taken from what the API handed over
     * when it is the same, resolved otherwise. A hand-over serves once.
     *
     * @throws InvalidBundleCompositionException
     */
    public function forAddition(int $productId, ?BundleComposition $composition): ?BundleComposition
    {
        $key = self::keyOf($productId, $composition);

        if (\array_key_exists($key, $this->handedOver)) {
            $handedOver = $this->handedOver[$key];
            unset($this->handedOver[$key]);

            return $handedOver;
        }

        return $this->resolve($productId, $composition);
    }

    public function reset(): void
    {
        $this->handedOver = [];
    }

    private function resolve(int $productId, ?BundleComposition $proposed): ?BundleComposition
    {
        // The state of the catalog spares the query only when nothing is proposed: a composition
        // proposed is judged on the data, never refused on a state that may lag behind it.
        if ((null === $proposed && !$this->bundleCatalogState->hasBundles()) || [] === $this->bundleItemRepository->bundleProductIds([$productId])) {
            if (null !== $proposed) {
                throw InvalidBundleCompositionException::notABundle($productId);
            }

            return null;
        }

        return $this->bundleCompositionResolver->resolve($productId, $proposed);
    }

    private static function keyOf(int $productId, ?BundleComposition $composition): string
    {
        return $productId.'|'.($composition?->toCanonicalJson() ?? '');
    }
}
