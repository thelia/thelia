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

use Thelia\Test\ApiTestCase;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The query behind a translatable resource left-joins every active language, so
 * a language without a translation comes back as an empty column — an answer,
 * not a missing one. Reading it as "not loaded yet" sent the resource back to
 * the database for a row that is not there, once per row and per language: 318
 * translation reads on the home page of the demo shop, which ships two
 * languages nobody translated.
 */
final class CategoryTranslationJoinQueryCountTest extends ApiTestCase
{
    use RecordsSqlQueries;

    public function testALanguageWithoutTranslationCostsNoQuery(): void
    {
        $factory = $this->createFixtureFactory();
        $factory->lang(['title' => 'Spanish', 'code' => 'es', 'locale' => 'es_ES']);

        $category = $factory->category();
        $category->setLocale('en_US')->setTitle('Garden tools');
        $category->save($this->getPropelConnection());

        $payload = [];
        $statements = $this->recordSqlQueries(function () use (&$payload, $category): void {
            $response = $this->jsonRequest('GET', '/api/front/categories/'.$category->getId());
            self::assertJsonResponseSuccessful($response);
            $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        });

        self::assertSame('Garden tools', $payload['i18ns']['en_US']['title'] ?? null);
        self::assertArrayNotHasKey('es_ES', $payload['i18ns'] ?? []);

        self::assertSame(
            0,
            self::countSqlQueriesSelectingFrom($statements, 'category_i18n'),
            'The translations were joined by the main query: none of them may be read again.',
        );
    }
}
