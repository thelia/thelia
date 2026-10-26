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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;

final readonly class VisitorCountryDetector
{
    /**
     * @param iterable<VisitorCountryResolverInterface> $resolvers
     */
    public function __construct(
        #[AutowireIterator(VisitorCountryResolverInterface::TAG)]
        private iterable $resolvers,
    ) {
    }

    /**
     * The country of the first resolver whose code is two letters and names a visible
     * country. A forged, unknown or hidden code is skipped without a word: it is the
     * visitor's input, not an error of the shop.
     */
    public function detect(Request $request): ?Country
    {
        foreach ($this->resolvers as $resolver) {
            $code = IsoCountryCode::normalize($resolver->resolve($request));

            if (null === $code) {
                continue;
            }

            $country = CountryQuery::create()->filterByVisible(1)->findOneByIsoalpha2($code);

            if (null !== $country) {
                return $country;
            }
        }

        return null;
    }
}
