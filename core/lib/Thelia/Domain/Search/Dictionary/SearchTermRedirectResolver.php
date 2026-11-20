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

namespace Thelia\Domain\Search\Dictionary;

use Thelia\Domain\Search\Contract\SearchRedirectResolverInterface;

final readonly class SearchTermRedirectResolver implements SearchRedirectResolverInterface
{
    public function __construct(
        private SearchDictionary $dictionary,
        private SearchRedirectTargets $targets,
    ) {
    }

    public function redirectFor(string $term, ?string $locale): ?string
    {
        $key = SearchTermNormalizer::key($term);

        if ('' === $key) {
            return null;
        }

        $redirect = $this->dictionary->redirectOf($key, $locale);

        if (null === $redirect) {
            return null;
        }

        return $this->targets->pathOf($redirect['type'], $redirect['id'], $redirect['url'], $locale);
    }
}
