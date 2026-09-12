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

use Thelia\Test\ApiTestCase;

/**
 * The title of a type is what heads the block it opens on a product sheet: a
 * type written with none is refused rather than saved untitled.
 */
final class ProductAssociationTypeNeedsTitleApiTest extends ApiTestCase
{
    public function testCreatingATypeWithoutAnyWordingIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $this->client->catchExceptions(true);

        $response = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => 'pipe_v6_5',
            'visible' => true,
            'reciprocal' => false,
        ], $token);

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A type with no title heads a front-office block with nothing: it is refused. Answer was: '
            .substr((string) $response->getContent(), 0, 300),
        );
    }
}
