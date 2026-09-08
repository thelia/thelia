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

use Thelia\Model\Feature;
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureProduct;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A product read returns its characteristics, and each of them names a feature
 * and a value living in their own tables. The relation holding them is a
 * collection, so the query cannot join it without duplicating the product row,
 * and nothing below that collection is joined either: every characteristic was
 * asked for its feature, its value and a translation of each in every active
 * language. A product carrying twelve characteristics spent forty-eight
 * queries on them.
 */
final class ProductFeaturePreloadTest extends ApiTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    private const FEATURE_COUNT = 6;

    private const READ_ONCE_PER_PRODUCT = [
        'feature',
        'feature_i18n',
        'feature_av',
        'feature_av_i18n',
    ];

    public function testTheSingleReadResolvesEachFeatureTableOnceForTheWholeProduct(): void
    {
        $product = $this->productWithFeatures();

        $payload = [];
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use ($product, &$payload): void {
            $payload = $this->readJson('/api/front/products/'.$product->getId());
        });

        self::assertCount(
            self::FEATURE_COUNT,
            $payload['featureProducts'] ?? [],
            'The read must return every characteristic, otherwise nothing is measured.',
        );

        $reads = [];

        foreach (self::READ_ONCE_PER_PRODUCT as $table) {
            $reads[$table] = self::countSqlQueriesSelectingFrom($statements, $table);
        }

        self::assertSame(
            array_fill_keys(self::READ_ONCE_PER_PRODUCT, 1),
            $reads,
            'Each table behind the characteristics is one read for the product, not one read per characteristic.',
        );
    }

    /**
     * The rows have to be the same ones, only fetched together.
     */
    public function testTheFeaturesAndTheirTranslationsAreStillReturned(): void
    {
        $product = $this->productWithFeatures();

        $payload = [];
        $this->recordSqlQueriesWithoutPooledModels(function () use ($product, &$payload): void {
            $payload = $this->readJson('/api/front/products/'.$product->getId());
        });

        $titles = [];

        foreach ($payload['featureProducts'] as $featureProduct) {
            foreach ($this->activeLocales() as $locale) {
                $titles[] = [
                    $featureProduct['feature']['i18ns'][$locale]['title'] ?? null,
                    $featureProduct['featureAv']['i18ns'][$locale]['title'] ?? null,
                ];
            }
        }

        $expected = [];

        foreach (range(1, self::FEATURE_COUNT) as $index) {
            foreach ($this->activeLocales() as $locale) {
                $expected[] = ['Feature '.$index.' '.$locale, 'Value '.$index.' '.$locale];
            }
        }

        self::assertSame($expected, $titles);
    }

    private function productWithFeatures(): Product
    {
        $connection = $this->getPropelConnection();
        $factory = $this->createFixtureFactory();

        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());

        for ($index = 1; $index <= self::FEATURE_COUNT; ++$index) {
            $feature = $factory->feature(['title' => 'Feature '.$index.' en_US']);
            $featureAv = $factory->featureAv($feature, ['title' => 'Value '.$index.' en_US']);

            $this->translate($feature, 'Feature '.$index);
            $this->translate($featureAv, 'Value '.$index);

            $featureProduct = new FeatureProduct();
            $featureProduct->setProductId($product->getId());
            $featureProduct->setFeatureId($feature->getId());
            $featureProduct->setFeatureAvId($featureAv->getId());
            $featureProduct->setPosition($index);
            $featureProduct->save($connection);
        }

        return $product;
    }

    /**
     * A translation missing in one language is read one row at a time whether
     * the page batched the others or not, so the product under test is
     * translated everywhere: what is measured is then the batch alone.
     */
    private function translate(Feature|FeatureAv $model, string $title): void
    {
        foreach ($this->activeLocales() as $locale) {
            $model->setLocale($locale)->setTitle($title.' '.$locale);
        }

        $model->save($this->getPropelConnection());
    }

    /**
     * @return list<string>
     */
    private function activeLocales(): array
    {
        return array_map(
            static fn (Lang $lang): string => (string) $lang->getLocale(),
            iterator_to_array(Lang::getActiveLangs()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $uri): array
    {
        $response = $this->jsonRequest('GET', $uri);
        self::assertJsonResponseSuccessful($response);

        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
