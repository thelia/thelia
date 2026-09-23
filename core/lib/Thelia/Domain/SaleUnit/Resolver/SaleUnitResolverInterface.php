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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\SaleUnit;

/**
 * One way of telling which sale unit a request sells from. The resolvers run
 * by descending priority and the first non-null answer wins: the core ships
 * the domain (128), the session (64) and the API header (32), and a project
 * with a strategy of its own (a store picker, a geolocation) registers one
 * more at the priority where it belongs.
 *
 * A resolver answers active sale units only: an answer here becomes the
 * current sale unit of the whole request.
 */
#[AutoconfigureTag(self::TAG)]
interface SaleUnitResolverInterface
{
    public const TAG = 'thelia.sale_unit.resolver';

    public function resolve(Request $request): ?SaleUnit;
}
