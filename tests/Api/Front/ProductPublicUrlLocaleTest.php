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

use Thelia\Model\Product;
use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * A rewritten url belongs to a locale, so `publicUrl` can only be answered in
 * one. The transformer never told the model which one, so the getter read back
 * whatever the translation loop had left there: a shop whose default language
 * is English answered French urls, and every row paid a rewriting_url read for
 * a locale nobody had asked for.
 */
final class ProductPublicUrlLocaleTest extends ApiTestCase
{
    use RecordsSqlQueries;

    private const DEFAULT_LOCALE = 'en_US';
    private const REQUESTED_LOCALE = 'es_ES';
    private const OTHER_LOCALE = 'fr_FR';
    private const STALE_LOCALE = 'it_IT';

    public function testTheSingleReadAnswersTheUrlOfTheRequestedLocale(): void
    {
        $product = $this->translatedProduct();

        $payload = $this->readJson('/api/front/products/'.$product->getId().'?locale='.self::REQUESTED_LOCALE);

        self::assertSame($product->getUrl(self::REQUESTED_LOCALE), $payload['publicUrl'] ?? null);
    }

    public function testTheCollectionAnswersTheUrlOfTheRequestedLocale(): void
    {
        $product = $this->translatedProduct();

        $payload = $this->readJson('/api/front/products?locale='.self::REQUESTED_LOCALE);

        self::assertSame(
            $product->getUrl(self::REQUESTED_LOCALE),
            $this->member($payload, $product->getId())['publicUrl'] ?? null,
        );
    }

    /**
     * A read carrying no locale has only the shop's default language to answer
     * in, which is the one the unreachable fallback of the getter already named.
     */
    public function testAReadWithoutALocaleAnswersTheShopDefault(): void
    {
        $product = $this->translatedProduct();

        $payload = $this->readJson('/api/front/products/'.$product->getId());

        self::assertSame($product->getUrl(self::DEFAULT_LOCALE), $payload['publicUrl'] ?? null);
    }

    /**
     * The batch that fills the rewritten url memo for a whole page fills it for
     * the locale being served. A getter asking for another one walks past the
     * memo and reads the table row by row, so the locale it asks for is also
     * what makes the batch effective.
     */
    public function testItDoesNotReadTheRewritingTableForALocaleNobodyAskedFor(): void
    {
        $product = $this->translatedProduct();

        $statements = $this->recordSqlQueries(function () use ($product): void {
            $this->readJson('/api/front/products/'.$product->getId().'?locale='.self::REQUESTED_LOCALE);
        });

        $rewritingReads = array_values(array_filter(
            $statements,
            static fn (string $statement): bool => str_contains($statement, 'FROM `rewriting_url`'),
        ));

        self::assertNotEmpty($rewritingReads, 'The read does resolve a public url, so it must read the table.');

        foreach ($rewritingReads as $statement) {
            self::assertStringContainsString(
                self::REQUESTED_LOCALE,
                $statement,
                'A read answering '.self::REQUESTED_LOCALE.' must not look up another locale: '.$statement,
            );
        }
    }

    /**
     * ProductI18n::postInsert() writes the rewritten url of the locale it saves,
     * so a title in three languages is a product with a url in each.
     *
     * The model is left carrying a fourth, untranslated locale on purpose: what
     * a model happens to hold is exactly what must not decide the answer.
     */
    private function translatedProduct(): Product
    {
        $factory = $this->createFixtureFactory();

        $product = $factory->product(
            $factory->category(),
            $factory->taxRule(),
            $factory->currency(),
            ['title' => 'Wooden chair', 'locale' => self::DEFAULT_LOCALE],
        );

        $connection = $this->getPropelConnection();

        $product->setLocale(self::OTHER_LOCALE)->setTitle('Chaise en bois')->save($connection);
        $product->setLocale(self::REQUESTED_LOCALE)->setTitle('Silla de madera')->save($connection);
        $product->setLocale(self::STALE_LOCALE);

        return $product;
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

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function member(array $payload, ?int $productId): array
    {
        foreach ($payload['hydra:member'] ?? [] as $member) {
            if (($member['id'] ?? null) === $productId) {
                return $member;
            }
        }

        self::fail('The collection must return the product under test, otherwise nothing is measured.');
    }
}
