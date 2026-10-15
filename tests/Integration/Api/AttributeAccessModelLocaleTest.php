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

namespace Thelia\Tests\Integration\Api;

use Thelia\Api\Service\DataAccess\AttributeAccessService;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * attr('category', 'url') and the other attributes read by a getter that translates on its own answer in the language
 * of the page, like the texts: the model used to keep the default locale of its class, so the address of a French page
 * was the unrewritten en_US one, which a shop without en_US redirects to its home page.
 */
final class AttributeAccessModelLocaleTest extends IntegrationTestCase
{
    public function testTheUrlOfTheCategoryIsTheRewrittenOneOfThePageLanguage(): void
    {
        $french = LangQuery::create()->findOneByLocale('fr_FR');
        self::assertNotNull($french, 'the test shop has French');

        $category = $this->createFixtureFactory()->category();
        $category->setLocale('fr_FR')->setTitle('Pièces casque test')->save($this->getPropelConnection());
        $rewritten = $category->generateRewrittenUrl('fr_FR', $this->getPropelConnection());

        $request = static::getContainer()->get('request_stack')->getCurrentRequest();
        $request->getSession()->setLang($french);
        $request->attributes->set('category_id', $category->getId());
        $this->forgetResolvedObjects();

        $url = static::getContainer()->get(AttributeAccessService::class)->attributeCategory('url');

        self::assertStringEndsWith('/'.$rewritten, (string) $url);
        self::assertSame('Pièces casque test', static::getContainer()->get(AttributeAccessService::class)->attributeCategory('title'));
    }

    /**
     * The service keeps the object it resolved for the rest of the request (a static cache), the category of another
     * test included.
     */
    private function forgetResolvedObjects(): void
    {
        (new \ReflectionProperty(AttributeAccessService::class, 'dataAccessCache'))->setValue(null, []);
    }
}
