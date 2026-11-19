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

namespace Thelia\Tests\Api\Search;

use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargetType;
use Thelia\Domain\Search\Event\SearchTermRedirectWriteEvent;
use Thelia\Domain\Search\SearchService;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\SearchTermRedirectTableMap;
use Thelia\Model\SearchTermRedirectQuery;
use Thelia\Test\ApiTestCase;

final class SearchTermRedirectThroughTheApiTest extends ApiTestCase
{
    public function testACategoryDeletedThroughTheAdminApiStopsBeingARedirectionTarget(): void
    {
        $category = $this->createFixtureFactory()->category();
        $event = (new SearchTermRedirectWriteEvent())
            ->setLangId((int) LangQuery::create()->findOneByLocale('en_US')?->getId())
            ->setTerm('garden')
            ->setTargetType(SearchRedirectTargetType::CATEGORY)
            ->setTargetId($category->getId());
        static::getContainer()->get('event_dispatcher')->dispatch($event, TheliaEvents::SEARCH_TERM_REDIRECT_CREATE);
        $redirectId = $event->getRedirect()?->getId();

        $response = $this->jsonRequest('DELETE', '/api/admin/categories/'.$category->getId(), token: $this->authenticateAsAdmin());

        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());
        SearchTermRedirectTableMap::clearInstancePool();
        self::assertSame(0, SearchTermRedirectQuery::create()->findPk($redirectId)?->getActive());
        self::assertNull(static::getContainer()->get(SearchService::class)->redirectFor('garden', 'en_US'));
    }
}
