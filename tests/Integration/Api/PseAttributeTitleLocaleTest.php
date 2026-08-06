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

use Thelia\Api\Service\DataAccess\ProductSaleElementsAccessService;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeCombination;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * attrAvByProduct() feeds the variant selector of a product page: the attribute
 * names ("Size", "Colour") and their values. It used to read them in the shop's
 * default language whatever the language of the request, so a storefront browsed
 * in a secondary locale showed a fully translated page with untranslated variant
 * labels.
 *
 * Whether an attribute left untranslated in the requested locale then falls back
 * to the default language is governed by the back office "If a translation is
 * missing or incomplete" setting, as it is in ResourceService::formatI18ns().
 */
final class PseAttributeTitleLocaleTest extends IntegrationTestCase
{
    private ProductSaleElementsAccessService $pseAccess;
    private LangService $langService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pseAccess = static::getContainer()->get(ProductSaleElementsAccessService::class);
        $this->langService = static::getContainer()->get(LangService::class);
    }

    protected function tearDown(): void
    {
        // The config cache is static: left as-is it would outlive the transaction
        // rollback and leak this test's setting into the rest of the suite.
        ConfigQuery::resetCache();

        parent::tearDown();
    }

    public function testTitlesAreReadInTheRequestedLocale(): void
    {
        $productId = $this->createProductWithOneVariant(
            ['en_US' => 'Colour', 'fr_FR' => 'Couleur'],
            ['en_US' => 'Blue', 'fr_FR' => 'Bleu'],
        );

        $this->useLocale('fr_FR');

        $attributes = $this->pseAccess->attrAvByProduct($productId);

        self::assertCount(1, $attributes);

        $attribute = reset($attributes);
        self::assertSame('Couleur', $attribute['label']);
        self::assertSame('Bleu', $attribute['values'][0]['label']);
    }

    public function testUntranslatedTitlesFallBackWhenTheSettingAllowsIt(): void
    {
        $this->setTranslationFallback(Lang::REPLACE_BY_DEFAULT_LANGUAGE);

        // Translated in the default language only: this is what the fallback is for.
        $productId = $this->createProductWithOneVariant(
            ['en_US' => 'Colour'],
            ['en_US' => 'Blue'],
        );

        $this->useLocale('fr_FR');

        $attributes = $this->pseAccess->attrAvByProduct($productId);

        $attribute = reset($attributes);
        self::assertSame('Colour', $attribute['label']);
        self::assertSame('Blue', $attribute['values'][0]['label']);
    }

    /**
     * Strict mode leaves the label empty rather than borrowing another language —
     * it must not fall back, and it must not fail either.
     */
    public function testUntranslatedTitlesStayEmptyWhenTheSettingIsStrict(): void
    {
        $this->setTranslationFallback(Lang::STRICTLY_USE_REQUESTED_LANGUAGE);

        $productId = $this->createProductWithOneVariant(
            ['en_US' => 'Colour'],
            ['en_US' => 'Blue'],
        );

        $this->useLocale('fr_FR');

        $attributes = $this->pseAccess->attrAvByProduct($productId);

        $attribute = reset($attributes);
        self::assertSame('', $attribute['label']);
        self::assertSame('', $attribute['values'][0]['label']);
    }

    /**
     * @param array<string, string> $attributeTitles locale => title
     * @param array<string, string> $valueTitles     locale => title
     */
    private function createProductWithOneVariant(array $attributeTitles, array $valueTitles): int
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['isDefault' => true]);

        $attribute = new Attribute();
        $this->translate($attribute, $attributeTitles);
        $attribute->save($connection);

        $attributeAv = new AttributeAv();
        $attributeAv->setAttributeId($attribute->getId());
        $this->translate($attributeAv, $valueTitles);
        $attributeAv->save($connection);

        $combination = new AttributeCombination();
        $combination->setProductSaleElementsId($pse->getId());
        $combination->setAttributeId($attribute->getId());
        $combination->setAttributeAvId($attributeAv->getId());
        $combination->save($connection);

        return $product->getId();
    }

    /**
     * @param array<string, string> $titles locale => title
     */
    private function translate(Attribute|AttributeAv $record, array $titles): void
    {
        foreach ($titles as $locale => $title) {
            $record->setLocale($locale)->setTitle($title);
        }
    }

    private function useLocale(string $locale): void
    {
        $lang = LangQuery::create()->findOneByLocale($locale, $this->getPropelConnection());

        if (null === $lang) {
            $lang = $this->createFixtureFactory()->lang([
                'locale' => $locale,
                'code' => explode('_', $locale)[0],
                'title' => $locale,
            ]);
        }

        $this->langService->setLang($lang);
    }

    /**
     * ConfigQuery::read() only honours a stored value through the cache the kernel
     * primes at boot; on a cold read a stored "0" is falsy and collapses back to the
     * default. Priming the cache the way ConfigCacheService does is what makes the
     * strict setting observable here at all.
     */
    private function setTranslationFallback(int $mode): void
    {
        ConfigQuery::write('default_lang_without_translation', (string) $mode);

        $configs = [];
        foreach (ConfigQuery::create()->find() as $config) {
            $configs[$config->getName()] = $config->getValue();
        }

        ConfigQuery::initCache($configs);
    }
}
