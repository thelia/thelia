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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters\Interface;

/**
 * A filter that depends on data the core does not hold, such as the ratings a review module
 * collects. While that data is missing the filter stays inert by itself (it offers no value,
 * so no facet, and a query string naming it changes nothing), and isOffered() says so to the
 * back-office screen of the facets, which hides its row.
 */
interface TheliaOptionalFilterInterface
{
    public function isOffered(): bool;
}
