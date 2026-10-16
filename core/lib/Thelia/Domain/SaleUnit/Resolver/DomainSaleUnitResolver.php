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
use Thelia\Model\SaleUnit;
use Thelia\Model\SaleUnitQuery;

/**
 * The domain is the strongest identity a sale unit has: whatever a session
 * carries, a visitor on shop-b.example.com is on shop B. That is why this
 * resolver outranks the session one — switching domains must never serve the
 * previous shop from a sticky session.
 *
 * `sale_unit.url` holds the bare host (no scheme, no port), compared to the
 * request host the way the per-language domains are matched on `lang.url`.
 *
 * The host is what the client sent, in the Host header or, behind a proxy Symfony
 * trusts, in X-Forwarded-Host. A deployment serving several units must declare its
 * hosts (`framework.trusted_hosts`) and only the proxies it runs behind
 * (`framework.trusted_proxies`): otherwise a visitor picks the unit, and so its
 * prices and its stock, by forging the header.
 */
#[AsTaggedItem(priority: 128)]
readonly class DomainSaleUnitResolver implements SaleUnitResolverInterface
{
    public function resolve(Request $request): ?SaleUnit
    {
        return SaleUnitQuery::create()
            ->filterByActive(true)
            ->filterByUrl($request->getHost())
            ->findOne();
    }
}
