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

namespace Thelia\Tests\Api\Contract;

use Thelia\Core\Security\GuestToken;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * The line a guest token must never cross.
 *
 * A guest is a real customer row, loaded by the ordinary provider, and Customer answers
 * ROLE_CUSTOMER for every row it holds. So the whole of /api/front/account hangs off one
 * substitution — the token is rebuilt with ROLE_GUEST alone before it becomes effective —
 * and on ROLE_GUEST being outside every hierarchy. Either of those quietly undone opens
 * the orders, the addresses and the account of an identity nobody confirmed to whoever
 * typed the address. This contract fails loudly if that happens.
 */
final class GuestTokenAuthorizationContractTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function accountEndpointProvider(): iterable
    {
        yield 'the order list' => ['GET', '/api/front/account/orders'];
        yield 'a single order' => ['GET', '/api/front/account/orders/1'];
        yield 'the address book' => ['GET', '/api/front/account/addresses'];
        yield 'a single address' => ['GET', '/api/front/account/addresses/1'];
        yield 'writing an account address' => ['POST', '/api/front/account/addresses'];
        yield 'the account itself' => ['GET', '/api/front/account/customers/1'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accountEndpointProvider')]
    public function testAGuestTokenIsRefusedEverywhereUnderTheAccountPrefix(string $method, string $uri): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $response = $this->jsonRequest($method, $uri, token: $guest['token']);

        self::assertSame(
            403,
            $response->getStatusCode(),
            \sprintf('%s %s must stay closed to a guest token.', $method, $uri),
        );
    }

    public function testTheGuestTokenCarriesTheGuestRoleAndNoOther(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        self::assertSame(
            [GuestToken::ROLE],
            self::jwtPayload($guest['token'])['roles'] ?? null,
            'A guest token that also names ROLE_CUSTOMER is a guest token that opens the account.',
        );
    }

    public function testACustomerTokenStillReachesItsOwnAccount(): void
    {
        // The other half of the contract: narrowing the guest token must not have
        // narrowed the token of somebody who actually signed in.
        $response = $this->jsonRequest(
            'GET',
            '/api/front/account/orders',
            token: $this->authenticateAsCustomer(),
        );

        self::assertJsonResponseSuccessful($response);
    }

    /**
     * The guest customer resource declares no item read, so API Platform mints a
     * not-exposed route purely to have an IRI to put in @id. It has to stay unreadable:
     * a resource whose only purpose is to hand out a credential must not become a way
     * to look one up by customer id.
     */
    public function testTheIriRouteOfTheGuestResourceServesNothing(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        foreach ([null, $guest['token']] as $token) {
            self::assertSame(
                404,
                $this->jsonRequest('GET', '/api/guest_customers/'.$guest['id'], token: $token)->getStatusCode(),
            );
        }
    }

    public function testAGuestTokenDoesNotOpenTheAdminApi(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $response = $this->jsonRequest('GET', '/api/admin/customers', token: $guest['token']);

        self::assertSame(403, $response->getStatusCode());
    }
}
