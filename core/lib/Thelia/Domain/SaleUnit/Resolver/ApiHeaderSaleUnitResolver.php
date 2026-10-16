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
 * An API consumer with no domain and no session names the sale unit it sells
 * from by its code, in the X-Sale-Unit header. An unknown or inactive code
 * resolves nothing rather than throwing here: what an unresolved sale unit
 * means is the calling context's decision, not a transport concern.
 *
 * Any caller can send the header, so it only ever names a unit the shop sells from
 * publicly: an inactive unit is never served, and the domain and the session, which
 * rank higher, are never overridden by it.
 */
#[AsTaggedItem(priority: 32)]
readonly class ApiHeaderSaleUnitResolver implements SaleUnitResolverInterface
{
    public const HEADER = 'X-Sale-Unit';

    public function resolve(Request $request): ?SaleUnit
    {
        $code = $request->headers->get(self::HEADER);

        if (null === $code || '' === $code) {
            return null;
        }

        return SaleUnitQuery::create()
            ->filterByActive(true)
            ->filterByCode($code)
            ->findOne();
    }
}
