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

namespace Thelia\Tests\Unit\Domain\Search;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Search\Document\SearchQuery;

final class SearchQueryTest extends TestCase
{
    public function testASearchAsksForAHundredHitsAtMost(): void
    {
        self::assertSame(SearchQuery::MAX_LIMIT, (new SearchQuery('phone', 'en_US', limit: SearchQuery::MAX_LIMIT))->limit);

        $this->expectException(\InvalidArgumentException::class);

        new SearchQuery('phone', 'en_US', limit: SearchQuery::MAX_LIMIT + 1);
    }
}
