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

    private function switchToFrench(string $path): Lang|Response
    {
        return $this->langService->resolveFrontLanguageFromRequest(
            Request::create(self::CURRENT_DOMAIN.$path.'?lang=fr'),
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
