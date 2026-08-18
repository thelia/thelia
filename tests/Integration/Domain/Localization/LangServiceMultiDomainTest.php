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

namespace Thelia\Tests\Integration\Domain\Localization;

use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Category;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * With one_domain_foreach_lang enabled, switching language must keep the visitor on the
 * page they were reading whenever that page has a translation on the target domain.
 */
final class LangServiceMultiDomainTest extends IntegrationTestCase
{
    private const CURRENT_DOMAIN = 'http://en.example.com';
    private const TARGET_DOMAIN = 'http://fr.example.com';

    private LangService $langService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langService = new LangService(new RequestStack(), new NullLogger());

        ConfigQuery::write('rewriting_enable', '1');
        ConfigQuery::write('one_domain_foreach_lang', '1');
    }

    protected function tearDown(): void
    {
        // ConfigQuery memoizes values in a static cache that would outlive the
        // transaction rollback and leak into the next test.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testRedirectKeepsCurrentPageWhenATranslationExists(): void
    {
        $this->frenchLangOnTargetDomain();

        $category = $this->createFixtureFactory()->category();
        $englishUrl = $this->rewrittenUrl($category, 'en_US', 'Multi domain category');
        $frenchUrl = $this->rewrittenUrl($category, 'fr_FR', 'Categorie multi domaine');

        $response = $this->switchToFrench('/'.$englishUrl);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::TARGET_DOMAIN.'/'.$frenchUrl, $response->getTargetUrl());
    }

    public function testRedirectFallsBackToDomainRootWhenThePageHasNoTranslation(): void
    {
        $this->frenchLangOnTargetDomain();

        $category = $this->createFixtureFactory()->category();
        $englishUrl = $this->rewrittenUrl($category, 'en_US', 'Untranslated category');

        self::assertNull($category->getRewrittenUrl('fr_FR'));

        $response = $this->switchToFrench('/'.$englishUrl);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::TARGET_DOMAIN, $response->getTargetUrl());
    }

    public function testRedirectFallsBackToDomainRootWhenTheCurrentUrlIsUnknown(): void
    {
        $this->frenchLangOnTargetDomain();

        $response = $this->switchToFrench('/no-such-page.html');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::TARGET_DOMAIN, $response->getTargetUrl());
    }

    public function testRedirectFallsBackToDomainRootWhenRewritingIsDisabled(): void
    {
        ConfigQuery::write('rewriting_enable', '0');

        $this->frenchLangOnTargetDomain();

        $category = $this->createFixtureFactory()->category();
        $englishUrl = $this->rewrittenUrl($category, 'en_US', 'Rewriting disabled category');
        $this->rewrittenUrl($category, 'fr_FR', 'Categorie sans reecriture');

        $response = $this->switchToFrench('/'.$englishUrl);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::TARGET_DOMAIN, $response->getTargetUrl());
    }

    public function testRedirectKeepsTheQueryParametersOfThePageBeingRead(): void
    {
        $this->frenchLangOnTargetDomain();

        $category = $this->createFixtureFactory()->category();
        $englishUrl = $this->rewrittenUrl($category, 'en_US', 'Paginated category');
        $frenchUrl = $this->rewrittenUrl($category, 'fr_FR', 'Categorie paginee');

        $response = $this->switchToFrench('/'.$englishUrl, ['page' => '2', 'order' => 'price']);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            self::TARGET_DOMAIN.'/'.$frenchUrl,
            strtok($response->getTargetUrl(), '?'),
        );
        self::assertSame(
            ['order' => 'price', 'page' => '2'],
            self::queryParametersOf($response->getTargetUrl()),
        );
    }

    /**
     * The language parameters have been consumed here, and the url they point to already is
     * the one of the requested language. Carrying them over would also make a domain the
     * shop cannot match redirect to itself for as long as the browser follows.
     */
    public function testRedirectDropsTheLanguageParameters(): void
    {
        $this->frenchLangOnTargetDomain();

        $category = $this->createFixtureFactory()->category();
        $englishUrl = $this->rewrittenUrl($category, 'en_US', 'Category asked in two ways');
        $this->rewrittenUrl($category, 'fr_FR', 'Categorie demandee de deux facons');

        $response = $this->switchToFrench('/'.$englishUrl, ['locale' => 'fr_FR', 'utm_source' => 'newsletter']);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            ['utm_source' => 'newsletter'],
            self::queryParametersOf($response->getTargetUrl()),
        );
    }

    public function testNoRedirectWhenTheTargetIsThePageBeingServed(): void
    {
        // The domain of the language is the one being browsed, written in another case: the
        // string comparison on the host sees two different domains, and the redirect it
        // builds points at the very url that was asked for.
        $this->frenchLangOnTargetDomain()->setUrl('http://EN.EXAMPLE.COM')->save();

        $category = $this->createFixtureFactory()->category();
        $this->rewrittenUrl($category, 'en_US', 'Category on a shouted domain');
        $frenchUrl = $this->rewrittenUrl($category, 'fr_FR', 'Categorie sur un domaine crie');

        $lang = $this->switchToFrench('/'.$frenchUrl);

        self::assertInstanceOf(Lang::class, $lang);
        self::assertSame('fr_FR', $lang->getLocale());
    }

    public function testNoRedirectOnAFormPost(): void
    {
        // A browser replays a 301 as a GET without the body: redirecting the post of a
        // checkout would throw away the order instead of taking it.
        $this->frenchLangOnTargetDomain();

        $request = Request::create(self::CURRENT_DOMAIN.'/order/delivery?lang=fr', 'POST');

        $lang = $this->langService->resolveFrontLanguageFromRequest($request);

        self::assertInstanceOf(Lang::class, $lang);
        self::assertSame('fr_FR', $lang->getLocale());
    }

    private static function queryParametersOf(string $url): array
    {
        $parameters = [];
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $parameters);
        ksort($parameters);

        return $parameters;
    }

    private function switchToFrench(string $path, array $query = []): Lang|Response
    {
        $query['lang'] = 'fr';

        return $this->langService->resolveFrontLanguageFromRequest(
            Request::create(self::CURRENT_DOMAIN.$path.'?'.http_build_query($query)),
        );
    }

    private function frenchLangOnTargetDomain(): Lang
    {
        $lang = LangQuery::create()->findOneByLocale('fr_FR')
            ?? $this->createFixtureFactory()->lang(['title' => 'Français', 'code' => 'fr', 'locale' => 'fr_FR']);

        $lang->setActive(true)->setUrl(self::TARGET_DOMAIN)->save();

        return $lang;
    }

    private function rewrittenUrl(Category $category, string $locale, string $title): string
    {
        $category->setLocale($locale)->setTitle($title)->save();

        return $category->getRewrittenUrl($locale)
            ?? $category->generateRewrittenUrl($locale, $this->getPropelConnection());
    }
}
