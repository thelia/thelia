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

namespace Thelia\Domain\Ai;

/**
 * Where a provider processes the data, shown on each connection. The core prefers the EU (defaults and
 * presets) and warns about a connection outside it (transfer outside the EU, CLOUD Act) without refusing
 * it; only the optional setting "EU only", off by default, refuses it.
 */
enum AiJurisdiction: string
{
    case EuropeanUnion = 'eu';
    case OutsideEuropeanUnion = 'non_eu';
}
