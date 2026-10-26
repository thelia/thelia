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

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Closes the chain: a detection that reaches it has found nothing.
 */
#[AsTaggedItem(priority: -100)]
final readonly class NullCountryResolver implements VisitorCountryResolverInterface
{
    public function resolve(Request $request): ?string
    {
        return null;
    }
}
