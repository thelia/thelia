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

namespace Thelia\Domain\SaleUnit\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\SaleUnit\Resolver\SaleUnitResolverInterface;
use Thelia\Domain\SaleUnit\SaleUnitActivityChecker;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\SaleUnit;

/**
 * Resolves the sale unit a front request sells from and installs it: in the
 * request-scoped context every price, stock or order read of the request will
 * consult, and in the session so the next request of the same visitor starts
 * from it.
 *
 * With no active sale unit the whole method is one indexed existence check:
 * the single-shop mode costs nothing and behaves exactly as before. In
 * multi-sale-unit mode a request nothing resolves keeps a null context — the
 * services that cannot serve a national answer are the ones that refuse it.
 */
readonly class SaleUnitService
{
    /**
     * @param iterable<SaleUnitResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator(SaleUnitResolverInterface::TAG)]
        private iterable $resolvers,
        private SaleUnitContext $saleUnitContext,
        private SaleUnitActivityChecker $activityChecker,
    ) {
    }

    public function handleRequest(Request $request): void
    {
        if (!$this->activityChecker->isMultiSaleUnitMode()) {
            return;
        }

        foreach ($this->resolvers as $resolver) {
            $saleUnit = $resolver->resolve($request);

            if ($saleUnit instanceof SaleUnit) {
                $this->setCurrentSaleUnit($saleUnit, $request);

                return;
            }
        }
    }

    public function setCurrentSaleUnit(SaleUnit $saleUnit, ?Request $request = null): void
    {
        $this->saleUnitContext->setSaleUnit($saleUnit);

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if ($session instanceof Session) {
            $session->setSaleUnitId($saleUnit->getId());
        }
    }
}
