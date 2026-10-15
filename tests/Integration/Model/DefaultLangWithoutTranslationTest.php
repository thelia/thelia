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

namespace Thelia\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\CartItem;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Tools\ModelCriteriaTools;
use Thelia\Test\IntegrationTestCase;

/**
 * The `default_lang_without_translation` row of a fresh install holds the string '1'
 * (setup/insert.sql.tpl), and the back office stores '0' or '1'. A product translated only
 * in the default language (en_US) is read in de_DE with each stored value, and with no row.
 */
final class DefaultLangWithoutTranslationTest extends IntegrationTestCase
{
    private const CONFIG_NAME = 'default_lang_without_translation';
    private const TITLE = 'Leather jacket';

    /**
     * @return iterable<string, array{string|null, int}>
     */
    public static function storedModes(): iterable
    {
        yield 'stored replace' => ['1', Lang::REPLACE_BY_DEFAULT_LANGUAGE];
        yield 'stored strict' => ['0', Lang::STRICTLY_USE_REQUESTED_LANGUAGE];
        yield 'no row' => [null, Lang::REPLACE_BY_DEFAULT_LANGUAGE];
    }

    #[DataProvider('storedModes')]
    public function testACartItemShowsItsProductInTheDefaultLanguageOnlyWhenMissingTranslationsAreReplaced(?string $stored, int $mode): void
    {
        $product = $this->createProductTranslatedOnlyInTheDefaultLanguage();
        $this->storeMode($stored);

        $cartItem = new CartItem();
        $cartItem->setProduct($product);

        $shown = $cartItem->getProduct(null, 'de_DE');

        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE === $mode) {
            self::assertSame('en_US', $shown->getLocale());
            self::assertSame(self::TITLE, $shown->getTitle());
        } else {
            self::assertSame('de_DE', $shown->getLocale());
            self::assertNull($shown->getTitle());
        }
    }

    #[DataProvider('storedModes')]
    public function testALoopQueryFindsAnUntranslatedProductOnlyWhenMissingTranslationsAreReplaced(?string $stored, int $mode): void
    {
        $product = $this->createProductTranslatedOnlyInTheDefaultLanguage();
        $this->storeMode($stored);

        $search = ProductQuery::create()->filterById($product->getId());
        ModelCriteriaTools::getFrontEndI18n($search, 'de_DE', ['TITLE'], null, 'ID');

        $found = $search->findOne($this->getPropelConnection());

        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE === $mode) {
            self::assertNotNull($found);
            self::assertSame(self::TITLE, $found->getVirtualColumn('i18n_TITLE'));
        } else {
            self::assertNull($found);
        }
    }

    private function createProductTranslatedOnlyInTheDefaultLanguage(): Product
    {
        $factory = $this->createFixtureFactory();

        return $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), [
            'title' => self::TITLE,
            'locale' => 'en_US',
        ]);
    }

    private function storeMode(?string $stored): void
    {
        if (null === $stored) {
            ConfigQuery::create()->filterByName(self::CONFIG_NAME)->delete($this->getPropelConnection());
        } else {
            ConfigQuery::write(self::CONFIG_NAME, $stored);
        }

        ConfigQuery::resetCache();
    }
}
