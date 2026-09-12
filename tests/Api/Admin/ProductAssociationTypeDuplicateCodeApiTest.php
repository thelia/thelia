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

use Thelia\Model\ProductAssociationType;
use Thelia\Test\ApiTestCase;

/**
 * The code of a type is unique; asking for one that is already taken is a
 * merchant mistake, answered as such.
 */
final class ProductAssociationTypeDuplicateCodeApiTest extends ApiTestCase
{
    public function testCreatingATypeWithACodeAlreadyTakenIsRefused(): void
    {
        $token = $this->authenticateAsAdmin();

        $this->client->catchExceptions(true);

        $response = $this->jsonRequest('POST', '/api/admin/product_association_types', [
            'code' => ProductAssociationType::CODE_ACCESSORY,
            'visible' => true,
            'reciprocal' => false,
            'i18ns' => ['en_US' => ['title' => 'Accessories, again']],
        ], $token);

        self::assertSame(
            422,
            $response->getStatusCode(),
            'A code already taken is refused as unprocessable, not answered by a 500: '
            .substr((string) $response->getContent(), 0, 300),
        );
    }
}
