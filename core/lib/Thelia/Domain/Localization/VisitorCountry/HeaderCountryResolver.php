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
 * The country a CDN or a reverse proxy already worked out and passed on in a header.
 */
#[AsTaggedItem(priority: 20)]
final readonly class HeaderCountryResolver implements VisitorCountryResolverInterface
{
    public function __construct(private VisitorCountrySettings $settings)
    {
    }

    public function resolve(Request $request): ?string
    {
        if (VisitorCountrySource::Header !== $this->settings->source()) {
            return null;
        }

        $value = $request->headers->get($this->settings->header());

        return \is_string($value) ? $value : null;
    }
}
