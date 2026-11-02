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

namespace Thelia\Tests\Integration\Domain\Ai;

use Thelia\Domain\Ai\Guardrail\AiHtmlSanitizer;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\SaleUnit;
use Thelia\Test\IntegrationTestCase;

/**
 * Without a list of its own, the sanitizer keeps the media of the hosts of the shop, read from its settings.
 */
final class AiHtmlSanitizerShopHostsTest extends IntegrationTestCase
{
    private string $siteUrl;

    private string $oneDomainForEachLanguage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siteUrl = (string) ConfigQuery::read('url_site', '');
        $this->oneDomainForEachLanguage = (string) ConfigQuery::read('one_domain_foreach_lang', '0');
    }

    protected function tearDown(): void
    {
        // The rows roll back with the transaction, the cache of ConfigQuery does not.
        ConfigQuery::write('url_site', $this->siteUrl);
        ConfigQuery::write('one_domain_foreach_lang', $this->oneDomainForEachLanguage);

        parent::tearDown();
    }

    public function testTheMediaOfTheHostsOfTheShopAreKeptAndNoOther(): void
    {
        ConfigQuery::write('url_site', 'https://www.shop.example');
        ConfigQuery::write('one_domain_foreach_lang', '1');
        $language = LangQuery::create()->filterByByDefault(0)->findOne() ?? self::fail('The demo has a second language.');
        $language->setUrl('https://de.shop-de.example')->save();
        (new SaleUnit())->setCode('ai-sanitizer-unit')->setUrl('shop-b.example')->setActive(false)->save();

        $clean = (new AiHtmlSanitizer())->sanitize(
            '<img src="https://www.shop.example/a.png"><img src="https://de.shop-de.example/b.png"><img src="https://shop-b.example/c.png"><img src="/d.png"><img src="https://tracker.example/p.gif?d=1">',
        );

        foreach (['https://www.shop.example/a.png', 'https://de.shop-de.example/b.png', 'https://shop-b.example/c.png', '/d.png'] as $kept) {
            self::assertStringContainsString('src="'.$kept.'"', $clean);
        }

        self::assertStringNotContainsString('tracker.example', $clean);
    }

    public function testTheDomainsOfTheLanguagesCountOnlyWhenEachLanguageHasItsOwn(): void
    {
        ConfigQuery::write('url_site', 'https://www.shop.example');
        ConfigQuery::write('one_domain_foreach_lang', '0');
        $language = LangQuery::create()->filterByByDefault(0)->findOne() ?? self::fail('The demo has a second language.');
        $language->setUrl('https://de.shop-de.example')->save();

        self::assertStringNotContainsString('shop-de.example', (new AiHtmlSanitizer())->sanitize('<img src="https://de.shop-de.example/b.png">'));
    }
}
