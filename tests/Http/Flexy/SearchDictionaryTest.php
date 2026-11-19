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

namespace Thelia\Tests\Http\Flexy;

use FlexyBundle\EventListener\SearchRedirectSubscriber;
use FlexyBundle\Service\ProductSearch;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Search\Dictionary\SearchRedirectTargetType;
use Thelia\Domain\Search\Event\SearchSynonymWriteEvent;
use Thelia\Domain\Search\Event\SearchTermRedirectWriteEvent;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\WebIntegrationTestCase;

final class SearchDictionaryTest extends WebIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(SearchRedirectSubscriber::class) || !method_exists(ProductSearch::class, 'titleFilter')) {
            self::markTestSkipped('The installed front-office theme does not apply the search dictionary of the core.');
        }
    }

    public function testARedirectedTermLandsOnItsCategory(): void
    {
        $category = $this->createFixtureFactory()->category();
        $event = (new SearchTermRedirectWriteEvent())
            ->setLangId($this->english()->getId())
            ->setTerm('garden sale')
            ->setTargetType(SearchRedirectTargetType::CATEGORY)
            ->setTargetId($category->getId());
        $this->getService('event_dispatcher')->dispatch($event, TheliaEvents::SEARCH_TERM_REDIRECT_CREATE);

        $this->dropRequestsWithoutSession();
        $this->client->request('GET', '/search?query='.rawurlencode('Garden  Sale'));

        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirection(), (string) $response->getStatusCode());
        self::assertSame(
            parse_url((string) $category->getUrl('en_US'), \PHP_URL_PATH),
            parse_url((string) $response->headers->get('Location'), \PHP_URL_PATH),
        );
    }

    public function testASynonymFindsTheProductsTitledWithItsEquivalent(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();
        $factory->product($category, $taxRule, $currency, ['title' => 'Dictionary smartphone']);
        $factory->product($category, $taxRule, $currency, ['title' => 'Mobilephone dictionary case']);
        $event = (new SearchSynonymWriteEvent())
            ->setLangId($this->english()->getId())
            ->setTerm('mobilephone')
            ->setSynonyms(['smartphone']);
        $this->getService('event_dispatcher')->dispatch($event, TheliaEvents::SEARCH_SYNONYM_CREATE);

        $this->dropRequestsWithoutSession();
        $this->assertPageRenders('/search?query=mobilephone');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Dictionary smartphone', $content);
        self::assertStringContainsString('Mobilephone dictionary case', $content);
    }

    private function dropRequestsWithoutSession(): void
    {
        $requestStack = $this->getService(RequestStack::class);

        while (($request = $requestStack->getCurrentRequest()) instanceof Request && !$request->hasSession()) {
            $requestStack->pop();
        }
    }

    private function english(): Lang
    {
        return LangQuery::create()->findOneByLocale('en_US') ?? throw new \LogicException('en_US is missing.');
    }
}
