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

namespace Thelia\Domain\Localization\VisitorCountry;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * A source of the country of a visitor. A module adds one by implementing this
 * interface: the detector asks every resolver in tag priority order and keeps the first
 * code that names a visible country.
 */
#[AutoconfigureTag(VisitorCountryResolverInterface::TAG)]
interface VisitorCountryResolverInterface
{
    public const TAG = 'thelia.visitor_country_resolver';

    /**
     * @return string|null the ISO 3166-1 alpha-2 code, as the source gave it, or null when
     *                     this source has nothing to say
     */
    public function resolve(Request $request): ?string;
}
