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

namespace Thelia\Domain\SaleUnit\Resolver;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitQuery;

/**
 * The sale unit a previous request of this session resolved. Only its id is
 * kept in the session, and the row is read and checked again on every request:
 * a sale unit turned off in the back office stops being served on the very
 * next click, not when the session dies.
 */
#[AsTaggedItem(priority: 64)]
readonly class SessionSaleUnitResolver implements SaleUnitResolverInterface
{
    public function resolve(Request $request): ?SaleUnit
    {
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        if (!$session instanceof Session) {
            return null;
        }

        $saleUnitId = $session->getSaleUnitId();

        if (null === $saleUnitId) {
            return null;
        }

        return SaleUnitQuery::create()
            ->filterByActive(true)
            ->findPk($saleUnitId);
    }
}
