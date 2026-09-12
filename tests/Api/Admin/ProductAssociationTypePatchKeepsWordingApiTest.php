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

namespace Thelia\Tests\Api\Admin;

use Thelia\Model\Map\ProductAssociationTypeI18nTableMap;
use Thelia\Model\ProductAssociationTypeI18nQuery;
use Thelia\Test\ApiTestCase;

/**
 * Hiding a relation type through the admin API must not cost it its wording.
 */
final class ProductAssociationTypePatchKeepsWordingApiTest extends ApiTestCase
{
    public function testHidingATypeLeavesItsWordingAlone(): void
    {
        $token = $this->authenticateAsAdmin();

        $created = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'pipe_v6_1',
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => [
                'en_US' => ['title' => 'Goes well with'],
                'fr_FR' => ['title' => 'Va bien avec'],
            ],
        ], $token);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());

        $id = (int) (json_decode((string) $created->getContent(), true)['id'] ?? 0);

        self::assertSame('Goes well with', $this->wording($id, 'en_US'));

        $patched = $this->jsonRequest(
            'PATCH',
            '/api/admin/product_association_types/'.$id,
            ['visible' => false],
            $token,
            'merge-patch+json',
        );

        self::assertSame(200, $patched->getStatusCode(), (string) $patched->getContent());

        self::assertSame(
            'Goes well with',
            $this->wording($id, 'en_US'),
            'A patch that carries no wording must leave the wording of every language where it was.',
        );
        self::assertSame('Va bien avec', $this->wording($id, 'fr_FR'));
    }

    private function wording(int $id, string $locale): ?string
    {
        ProductAssociationTypeI18nTableMap::clearInstancePool();

        return ProductAssociationTypeI18nQuery::create()
            ->filterById($id)
            ->filterByLocale($locale)
            ->findOne()
            ?->getTitle();
    }
}
