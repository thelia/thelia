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

use Thelia\Model\CartQuery;
use Thelia\Test\ApiTestCase;

/**
 * POST /api/front/carts is anonymous, so the body decides nothing about who
 * the cart belongs to: an unauthenticated caller must not be able to file a
 * cart under somebody else's account, nor to grant it a discount.
 */
final class CartCreationOwnershipApiTest extends ApiTestCase
{
    public function testAnonymousCallerCannotFileACartUnderAnotherCustomer(): void
    {
        $factory = $this->createFixtureFactory();
        $victim = $factory->customer($factory->customerTitle());

        $cartsBefore = CartQuery::create()
            ->filterByCustomerId($victim->getId())
            ->count($this->getPropelConnection());

        $this->jsonRequest('POST', '/api/front/carts', [
            'customer' => '/api/front/account/customers/'.$victim->getId(),
        ]);

        self::assertSame(
            $cartsBefore,
            CartQuery::create()
                ->filterByCustomerId($victim->getId())
                ->count($this->getPropelConnection()),
            'An anonymous POST must not attach a new cart to another customer.',
        );
    }

    public function testAnonymousCallerCannotGrantItselfADiscount(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/carts', ['discount' => 999.0]);

        if (!\in_array($response->getStatusCode(), [200, 201], true)) {
            self::assertContains($response->getStatusCode(), [400, 403, 422]);

            return;
        }

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertNotSame(
            999.0,
            (float) ($data['discount'] ?? 0.0),
            'The body must not be able to set the cart discount.',
        );
    }

    public function testTheCartTokenIsServerGeneratedNotChosenByTheBody(): void
    {
        $chosen = 'attacker-chosen-token-value';

        $response = $this->jsonRequest('POST', '/api/front/carts', ['token' => $chosen]);
        self::assertContains($response->getStatusCode(), [200, 201]);

        $data = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $token = CartQuery::create()
            ->findPk((int) $data['id'], $this->getPropelConnection())
            ?->getToken();

        // The token is a bearer secret restored from a cookie: the body must not
        // be able to fix it, and the server must always assign an unguessable one.
        self::assertNotEmpty($token, 'A created cart must carry a server-generated token.');
        self::assertNotSame($chosen, $token, 'The body must not be able to choose the cart token.');
    }
}
