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

namespace Thelia\Tests\Api\Front;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Category;
use Thelia\Model\Product;
use Thelia\Model\TaxRule;
use Thelia\Test\ApiTestCase;

/**
 * Sorting of the public product collection: creation date and translated title.
 */
final class ProductSortApiTest extends ApiTestCase
{
    public function testOrderByCreatedAtPutsTheNewestProductFirst(): void
    {
        $this->seedDatedProducts();

        self::assertSame(['DATE-OLD', 'DATE-MID', 'DATE-NEW'], $this->refsOf('order[createdAt]=asc'));
        self::assertSame(['DATE-NEW', 'DATE-MID', 'DATE-OLD'], $this->refsOf('order[createdAt]=desc'));
    }

    public function testOrderByUpdatedAt(): void
    {
        $this->seedDatedProducts();

        self::assertSame(['DATE-NEW', 'DATE-MID', 'DATE-OLD'], $this->refsOf('order[updatedAt]=asc'));
        self::assertSame(['DATE-OLD', 'DATE-MID', 'DATE-NEW'], $this->refsOf('order[updatedAt]=desc'));
    }

    public function testOrderByTitleFollowsTheRequestedLocale(): void
    {
        $this->seedTranslatedProducts();

        // en_US: Alpha < Beta < Zulu, the untranslated product last.
        self::assertSame(
            ['T-ALPHA', 'T-BETA', 'T-ZULU', 'T-NONE'],
            $this->refsOf('order[title]=asc&locale=en_US'),
        );

        // fr_FR: Ananas < Beta (no fr title, falls back to en) < Zebre.
        self::assertSame(
            ['T-ZULU', 'T-BETA', 'T-ALPHA', 'T-NONE'],
            $this->refsOf('order[title]=asc&locale=fr_FR'),
        );
    }

    public function testOrderByTitleDescendingKeepsTheUntranslatedProductLast(): void
    {
        $this->seedTranslatedProducts();

        self::assertSame(
            ['T-ZULU', 'T-BETA', 'T-ALPHA', 'T-NONE'],
            $this->refsOf('order[title]=desc&locale=en_US'),
        );
    }

    public function testSortingDoesNotChangeTheAnnouncedTotal(): void
    {
        $this->seedTranslatedProducts();

        $total = $this->totalOf('');

        self::assertSame(4, $total);
        self::assertSame($total, $this->totalOf('order[title]=asc&locale=en_US'));
        self::assertSame($total, $this->totalOf('order[title]=desc&locale=fr_FR'));
        self::assertSame($total, $this->totalOf('order[createdAt]=desc'));
    }

    public function testUnknownSortIsIgnored(): void
    {
        $this->seedTranslatedProducts();

        $default = $this->refsOf('');

        self::assertSame($default, $this->refsOf('order[bogus]=asc'));
        self::assertSame($default, $this->refsOf('order[title]=sideways&locale=en_US'));
        self::assertSame($default, $this->refsOf('order[createdAt]=sideways'));
    }

    /**
     * Sorting is not filtering: a product the merchant never priced must not vanish from the
     * listing the moment a visitor asks for a price order.
     */
    public function testOrderByPriceKeepsAProductWithoutASaleElement(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, ['ref' => 'PRICE-LOW', 'basePrice' => 5.0]);
        $factory->product($category, $taxRule, $currency, ['ref' => 'PRICE-HIGH', 'basePrice' => 50.0]);
        $this->productWithoutASaleElement($category, $taxRule, 'PRICE-NONE');

        $total = $this->totalOf('');

        self::assertSame(3, $total);
        self::assertSame($total, $this->totalOf('untaxed_price_order=asc'));
        self::assertSame($total, $this->totalOf('untaxed_price_order=desc'));

        // The priceless product is kept, and ranged last whichever way the prices are read.
        self::assertSame(['PRICE-LOW', 'PRICE-HIGH', 'PRICE-NONE'], $this->refsOf('untaxed_price_order=asc'));
        self::assertSame(['PRICE-HIGH', 'PRICE-LOW', 'PRICE-NONE'], $this->refsOf('untaxed_price_order=desc'));
    }

    /**
     * Built without the fixture factory: Product::create() always makes a default sale element
     * and its price, and this test is about a product that has neither.
     */
    private function productWithoutASaleElement(Category $category, TaxRule $taxRule, string $ref): Product
    {
        $product = new Product();
        $product
            ->setRef($ref)
            ->setVisible(1)
            ->setPosition(99)
            ->setTaxRuleId($taxRule->getId())
            ->save($this->getPropelConnection());

        $product->setDefaultCategory($category->getId())->save($this->getPropelConnection());

        return $product;
    }

    private function seedDatedProducts(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $factory->product($category, $taxRule, $currency, [
            'ref' => 'DATE-MID',
            'createdAt' => new \DateTime('2022-06-15 12:00:00'),
            'updatedAt' => new \DateTime('2022-06-15 12:00:00'),
        ]);
        $factory->product($category, $taxRule, $currency, [
            'ref' => 'DATE-NEW',
            'createdAt' => new \DateTime('2024-01-01 08:00:00'),
            'updatedAt' => new \DateTime('2020-01-01 08:00:00'),
        ]);
        $factory->product($category, $taxRule, $currency, [
            'ref' => 'DATE-OLD',
            'createdAt' => new \DateTime('2020-03-04 09:30:00'),
            'updatedAt' => new \DateTime('2024-03-04 09:30:00'),
        ]);
    }

    /**
     * Four products whose alphabetical order differs between en_US and fr_FR:
     * one is translated in en_US only, one has no translation at all.
     */
    private function seedTranslatedProducts(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $taxRule = $factory->taxRule();
        $currency = $factory->currency();

        $alpha = $factory->product($category, $taxRule, $currency, ['ref' => 'T-ALPHA', 'title' => 'Alpha']);
        $this->translate($alpha, 'fr_FR', 'Zebre');

        $zulu = $factory->product($category, $taxRule, $currency, ['ref' => 'T-ZULU', 'title' => 'Zulu']);
        $this->translate($zulu, 'fr_FR', 'Ananas');

        $factory->product($category, $taxRule, $currency, ['ref' => 'T-BETA', 'title' => 'Beta']);
        $factory->product($category, $taxRule, $currency, ['ref' => 'T-NONE']);
    }

    private function translate(Product $product, string $locale, string $title): void
    {
        $product->setLocale($locale)->setTitle($title);
        $product->save();
    }

    /**
     * @return array<int, string>
     */
    private function refsOf(string $query): array
    {
        $data = $this->decode($this->request($query));

        return array_column($data['hydra:member'], 'ref');
    }

    private function totalOf(string $query): int
    {
        return (int) $this->decode($this->request($query))['hydra:totalItems'];
    }

    private function request(string $query): Response
    {
        $response = $this->jsonRequest('GET', '/api/front/products'.('' === $query ? '' : '?'.$query));
        self::assertJsonResponseSuccessful($response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
