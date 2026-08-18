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

namespace Thelia\Tests\Integration\Core\Routing;

use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Routing\RewritingRouter;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

final class RewritingRouterTest extends IntegrationTestCase
{
    private RewritingRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        ConfigQuery::write('rewriting_enable', '1');

        $this->router = $this->getService('router.rewrite');
    }

    protected function tearDown(): void
    {
        // ConfigQuery memoizes values in a static cache that would outlive the
        // transaction rollback and leak into the next test.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    private function createCategory(string $title): Category
    {
        $category = $this->createFixtureFactory()->category();
        $category->setLocale('en_US')->setTitle($title)->save();

        return $category;
    }

    private function request(string $url, array $query = []): Request
    {
        $request = Request::create('/'.$url, 'GET', $query);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    /**
     * A request whose session language differs from the language of the url being asked for,
     * which is what makes the router look at the per language domains.
     */
    private function requestInFrench(string $url): Request
    {
        $request = $this->request($url);
        $request->getSession()->setLang(LangQuery::create()->findOneByLocale('fr_FR'));

        return $request;
    }

    private function langUrl(string $locale, string $url): void
    {
        LangQuery::create()->findOneByLocale($locale)->setActive(true)->setUrl($url)->save();
    }

    /**
     * The language is switched on the main request, the one LangService works with.
     */
    private function mainRequestLocale(): ?string
    {
        return $this->getService('request_stack')->getMainRequest()?->getSession()->getLang(false)?->getLocale();
    }

    public function testMatchRequestResolvesALiveUrl(): void
    {
        $category = $this->createCategory('Live category');
        $url = $category->getRewrittenUrl('en_US');

        $parameters = $this->router->matchRequest($this->request($url));

        self::assertTrue($parameters['_rewritten']);
    }

    public function testMatchRequestRejectsTheUrlOfADeletedObject(): void
    {
        $category = $this->createCategory('Deleted category');
        $url = $category->getRewrittenUrl('en_US');
        $category->delete();

        // Deleting the object does not delete its urls: they are kept under the
        // obsolete view. Nothing is left to serve, and nothing to redirect to.
        $this->expectException(ResourceNotFoundException::class);
        $this->router->matchRequest($this->request($url));
    }

    public function testMatchRequestRejectsTheUrlOfADeletedObjectAskedInAnotherLanguage(): void
    {
        $category = $this->createCategory('Deleted category asked in french');
        $url = $category->getRewrittenUrl('en_US');
        $category->delete();

        // Every deleted object shares the same obsolete view name, so looking up
        // "the same page in another language" used to answer with the url of an
        // unrelated object that happened to have the same id.
        $this->expectException(ResourceNotFoundException::class);
        $this->router->matchRequest($this->request($url, ['lang' => 'fr_FR']));
    }

    public function testMatchRequestDoesNotRedirectToItselfWhenTheLanguageHasNoDomain(): void
    {
        ConfigQuery::write('one_domain_foreach_lang', '1');
        $this->langUrl('en_US', '');

        $category = $this->createCategory('Category of a language without domain');
        $request = $this->requestInFrench($category->getRewrittenUrl('en_US'));

        // Every lang.url is empty on a fresh install: the redirect target used to be the
        // very url being asked for, and a browser follows it until it gives up.
        $parameters = $this->router->matchRequest($request);

        self::assertTrue($parameters['_rewritten']);
        self::assertSame('en_US', $this->mainRequestLocale());
    }

    public function testMatchRequestDoesNotRedirectToItselfWhenTheLanguageDomainIsTheCurrentOne(): void
    {
        ConfigQuery::write('one_domain_foreach_lang', '1');
        $this->langUrl('en_US', 'http://localhost');

        $category = $this->createCategory('Category of a language on the current domain');
        $request = $this->requestInFrench($category->getRewrittenUrl('en_US'));

        $parameters = $this->router->matchRequest($request);

        self::assertTrue($parameters['_rewritten']);
        self::assertSame('en_US', $this->mainRequestLocale());
    }

    public function testMatchRequestRedirectsToTheLanguageDomainWhenItDiffers(): void
    {
        ConfigQuery::write('one_domain_foreach_lang', '1');
        $this->langUrl('en_US', 'http://en.example.com');

        $category = $this->createCategory('Category of a language on its own domain');
        $url = $category->getRewrittenUrl('en_US');

        try {
            $this->router->matchRequest($this->requestInFrench($url));
            self::fail('An url of another language domain must redirect to that domain.');
        } catch (RedirectException $redirectException) {
            self::assertSame(301, $redirectException->getStatusCode());
            self::assertSame('http://en.example.com/'.$url, $redirectException->getUrl());
        }
    }

    public function testMatchRequestRedirectsToALanguageDomainWrittenWithATrailingSlash(): void
    {
        ConfigQuery::write('one_domain_foreach_lang', '1');
        $this->langUrl('en_US', 'http://en.example.com/');

        $category = $this->createCategory('Category of a language on its own slashed domain');
        $url = $category->getRewrittenUrl('en_US');

        try {
            $this->router->matchRequest($this->requestInFrench($url));
            self::fail('An url of another language domain must redirect to that domain.');
        } catch (RedirectException $redirectException) {
            self::assertSame('http://en.example.com/'.$url, $redirectException->getUrl());
        }
    }

    /**
     * @return array{0: string, 1: string} the french then the english url of the same category
     */
    private function createTranslatedCategory(string $title): array
    {
        $category = $this->createCategory($title);
        $category->setLocale('fr_FR')->setTitle($title.' en francais')->save();

        return [$category->getRewrittenUrl('fr_FR'), $category->getRewrittenUrl('en_US')];
    }

    private static function queryParametersOf(string $url): array
    {
        $parameters = [];
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $parameters);
        ksort($parameters);

        return $parameters;
    }

    public function testMatchRequestKeepsTheQueryParametersWhenSwitchingLanguage(): void
    {
        [$frenchUrl, $englishUrl] = $this->createTranslatedCategory('Category read in another language');

        try {
            $this->router->matchRequest(
                $this->request($frenchUrl, ['page' => '2', 'order' => 'price', 'lang' => 'en_US']),
            );
            self::fail('Asking for another language must redirect to the url of that language.');
        } catch (RedirectException $redirectException) {
            $target = $redirectException->getUrl();

            // The state of the page - here the page of a paginated listing and its sort order -
            // used to be dropped, sending the visitor back to the top of the first page.
            self::assertStringEndsWith('/'.$englishUrl, (string) parse_url($target, \PHP_URL_PATH));
            self::assertSame(['order' => 'price', 'page' => '2'], self::queryParametersOf($target));
        }
    }

    public function testMatchRequestDoesNotCarryRewritingParametersOverWhenSwitchingLanguage(): void
    {
        [$frenchUrl, $englishUrl] = $this->createTranslatedCategory('Category read with rewriting parameters');

        $request = $this->request($frenchUrl, ['page' => '2', 'lang' => 'en_US']);

        // What applyRewritingAttributes() writes into the query bag: the view id, and the
        // parameters encoded in the rewritten url. They are internal to the rewriting and
        // must never be handed back to the visitor as part of a public url.
        $request->query->set('category_id', '4242');
        $request->query->set('folder_id', '7');

        try {
            $this->router->matchRequest($request);
            self::fail('Asking for another language must redirect to the url of that language.');
        } catch (RedirectException $redirectException) {
            $target = $redirectException->getUrl();

            self::assertStringEndsWith('/'.$englishUrl, (string) parse_url($target, \PHP_URL_PATH));
            self::assertSame(['page' => '2'], self::queryParametersOf($target));
        }
    }

    public function testMatchRequestRedirectsAReplacedUrlToTheCurrentOne(): void
    {
        $category = $this->createCategory('Replaced category');
        $oldUrl = $category->getRewrittenUrl('en_US');

        $category->setRewrittenUrl('en_US', 'replaced-category-new-address.html');
        $newUrl = $category->getRewrittenUrl('en_US');

        self::assertNotSame($oldUrl, $newUrl);

        try {
            $this->router->matchRequest($this->request($oldUrl));
            self::fail('A replaced url must redirect to the current one.');
        } catch (RedirectException $redirectException) {
            self::assertSame(301, $redirectException->getStatusCode());
            self::assertStringEndsWith('/'.$newUrl, $redirectException->getUrl());
        }
    }
}
