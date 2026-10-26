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

#[AsTaggedItem(priority: 10)]
final readonly class IpDatabaseCountryResolver implements VisitorCountryResolverInterface
{
    public function __construct(
        private VisitorCountrySettings $settings,
        private IpCountryLookupInterface $lookup,
    ) {
    }

    public function resolve(Request $request): ?string
    {
        if (VisitorCountrySource::IpDatabase !== $this->settings->source()) {
            return null;
        }

        $ipAddress = $request->getClientIp();

        if (null === $ipAddress) {
            return null;
        }

        return $this->lookup->lookup($ipAddress, $this->settings->databaseFileName());
    }
}
