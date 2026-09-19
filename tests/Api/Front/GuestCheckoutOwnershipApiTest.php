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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\AddressQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\CountryQuery;
use Thelia\Model\CustomerTitle;
use Thelia\Model\OrderQuery;
use Thelia\Test\ApiTestCase;
use Thelia\Tests\Api\Trait\RegistersGuestCustomers;

/**
 * What a guest token reaches, and what it must not.
 *
 * Two guests are never the same visitor, and the awkward case is the one where they
 * share a customer row: the shop reuses the guest account behind an address, so
 * checking out as a guest with an address somebody already used lands on their row.
 * Matching the customer id is therefore not an answer to "is this mine" — the cart the
 * token names is.
 */
final class GuestCheckoutOwnershipApiTest extends ApiTestCase
{
    use RegistersGuestCustomers;

    public function testAGuestReadsItsOwnCart(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$guest['cartId'], token: $guest['token']);

        self::assertJsonResponseSuccessful($response);
    }

    public function testAGuestCannotReadTheCartOfAnotherGuest(): void
    {
        $this->enableGuestCheckout();

        [, $victim] = $this->registerGuestInAFreshSession();
        [, $attacker] = $this->registerGuestInAFreshSession();

        self::assertNotSame($victim['cartId'], $attacker['cartId']);

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$victim['cartId'], token: $attacker['token']);

        self::assertSame(403, $response->getStatusCode(), 'A guest token must not open another guest checkout.');
    }

    /**
     * The reuse case: both visitors give the same address, so the shop hands them the
     * same guest row — and, if ownership were decided on the customer id alone, each
     * other's carts.
     */
    public function testAGuestCannotReadTheCartOfAnEarlierVisitorSharingItsAccount(): void
    {
        $this->enableGuestCheckout();

        $sharedEmail = 'shared-guest-'.bin2hex(random_bytes(6)).'@test.com';

        [, $first] = $this->registerGuestInAFreshSession(['email' => $sharedEmail]);
        [, $second] = $this->registerGuestInAFreshSession(['email' => $sharedEmail]);

        self::assertSame($first['id'], $second['id'], 'The shop reuses the guest row behind an address.');
        self::assertNotSame($first['cartId'], $second['cartId'], 'Each visit checks out its own cart.');

        $response = $this->jsonRequest('GET', '/api/front/carts/'.$first['cartId'], token: $second['token']);

        self::assertSame(
            403,
            $response->getStatusCode(),
            'Sharing the guest account behind an address must not share the carts hanging off it.',
        );
    }

    public function testAGuestCannotEmptyTheCartOfAnotherGuest(): void
    {
        $this->enableGuestCheckout();

        [, $victim] = $this->registerGuestInAFreshSession();
        [, $attacker] = $this->registerGuestInAFreshSession();

        $response = $this->jsonRequest('DELETE', '/api/front/carts/'.$victim['cartId'], token: $attacker['token']);

        self::assertSame(403, $response->getStatusCode());
        self::assertNotNull(
            CartQuery::create()->findPk($victim['cartId'], $this->getPropelConnection()),
            'The refused delete must not have taken the cart with it.',
        );
    }

    public function testAGuestCannotReadTheCartItemsOfAnotherGuest(): void
    {
        $this->enableGuestCheckout();

        [, $victim] = $this->registerGuestInAFreshSession();

        $factory = $this->createFixtureFactory();
        $cart = CartQuery::create()->findPk($victim['cartId'], $this->getPropelConnection());
        self::assertNotNull($cart);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $cartItem = $factory->cartItem($cart, $product);

        [, $attacker] = $this->registerGuestInAFreshSession();

        $response = $this->jsonRequest('GET', '/api/front/cart_items/'.$cartItem->getId(), token: $attacker['token']);

        // 404, not 403: ownership of a cart line is a filter on the query, so a line
        // outside the token's cart is a line that does not exist as far as the caller is
        // concerned. Which is also the answer that gives nothing away.
        self::assertSame(
            404,
            $response->getStatusCode(),
            'A cart line belongs to the cart the token names, and to no other guest.',
        );
    }

    /**
     * Same reuse case, one layer down: the cart lines. Ownership of a cart line is
     * decided from the cart behind it, so if that answer were the customer id the two
     * visitors share, each of them would be reading the other's basket.
     */
    public function testAGuestCannotReachTheCartLinesOfAnEarlierVisitorSharingItsAccount(): void
    {
        $this->enableGuestCheckout();

        $sharedEmail = 'shared-lines-'.bin2hex(random_bytes(6)).'@test.com';
        $factory = $this->createFixtureFactory();

        [, $first] = $this->registerGuestInAFreshSession(['email' => $sharedEmail]);
        $firstCart = CartQuery::create()->findPk($first['cartId'], $this->getPropelConnection());
        self::assertNotNull($firstCart);
        $cartItem = $factory->cartItem(
            $firstCart,
            $factory->product($factory->category(), $factory->taxRule(), $factory->currency()),
        );

        [, $second] = $this->registerGuestInAFreshSession(['email' => $sharedEmail]);

        self::assertSame($first['id'], $second['id'], 'The shop reuses the guest row behind an address.');

        $item = $this->jsonRequest('GET', '/api/front/cart_items/'.$cartItem->getId(), token: $second['token']);

        self::assertSame(
            404,
            $item->getStatusCode(),
            'A cart line of an earlier visit must not be readable through the account it shares.',
        );

        $collection = $this->jsonRequest('GET', '/api/front/cart_items', token: $second['token']);

        self::assertJsonResponseSuccessful($collection);
        self::assertStringNotContainsString(
            '"id":'.$cartItem->getId(),
            (string) $collection->getContent(),
            'The cart line listing must not leak the basket of the earlier visit either.',
        );
    }

    /**
     * The order tunnel under `/front/account/checkout` is the tunnel of an account, and a
     * guest is not one — checking out without an account is another story, told by another
     * set of operations. A guest row carries ROLE_CUSTOMER like any other customer, so the
     * firewall lets the token through and the refusal has to happen in the code.
     *
     * The refusal is a 403 and it comes from the role, not from the code: a guest token is
     * pinned to ROLE_GUEST, which deliberately implies nothing, and every one of the six
     * operations demands ROLE_CUSTOMER. The ownership check inside them answers the same
     * caller with a 404 and never gets the chance — it stays as the second barrier, for
     * the day a guest is given a role that does imply ROLE_CUSTOMER.
     *
     * What matters either way is that the answer says nothing about the cart: the body for
     * the guest's own cart and for a cart that was never created are compared word for
     * word, because a refusal that reads differently is a way of counting the carts of the
     * shop.
     */
    public function testAGuestTokenReachesNoneOfTheAccountCheckoutOperations(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();
        $unknownCartId = 1 + (int) CartQuery::create()
            ->orderById(Criteria::DESC)
            ->findOne($this->getPropelConnection())
            ?->getId();

        foreach (self::everyCheckoutOperation() as $label => [$method, $step, $payload]) {
            $onItsOwnCart = $this->jsonRequest($method, $this->checkoutUrl($guest['cartId'], $step), $payload, token: $guest['token']);
            $onNothing = $this->jsonRequest($method, $this->checkoutUrl($unknownCartId, $step), $payload, token: $guest['token']);

            self::assertSame(403, $onItsOwnCart->getStatusCode(), \sprintf('"%s" must not be reachable with a guest token.', $label));
            self::assertSame(403, $onNothing->getStatusCode());
            self::assertSame(
                $this->comparableBody($onNothing),
                $this->comparableBody($onItsOwnCart),
                \sprintf('"%s" must answer a guest exactly as it answers a cart that does not exist.', $label),
            );
        }

        self::assertCount(
            0,
            OrderQuery::create()->filterByCartId($guest['cartId'])->find($this->getPropelConnection()),
            'A refused placement must not have written an order.',
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private static function everyCheckoutOperation(): iterable
    {
        // The identifiers are never read: the ownership check answers before the body is
        // looked at, which is the whole point of the operations refusing this token.
        yield 'delivery address' => ['POST', 'delivery_address', ['addressId' => 1]];
        yield 'invoice address' => ['POST', 'invoice_address', ['addressId' => 1]];
        yield 'delivery module' => ['POST', 'delivery_module', ['deliveryModuleId' => 1]];
        yield 'payment module' => ['POST', 'payment_module', ['paymentModuleId' => 1]];
        yield 'validation' => ['GET', 'validation', []];
        yield 'placement' => ['POST', 'place', []];
    }

    private function checkoutUrl(int $cartId, string $step): string
    {
        return '/api/front/account/checkout/'.$cartId.'/'.$step;
    }

    /**
     * The body of an error answer, with the fields that legitimately differ from one
     * request to the next taken out, so that two refusals can be compared word for word.
     *
     * @return array<string, mixed>
     */
    private function comparableBody(Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true) ?? [];

        unset($body['trace'], $body['hydra:trace'], $body['@id'], $body['hydra:description'], $body['detail']);

        // The description is the message, and comparing it is the whole point; it is put
        // back under one key so that a hydra body and a problem body compare the same.
        $message = json_decode((string) $response->getContent(), true) ?? [];
        $body['message'] = $message['hydra:description'] ?? $message['detail'] ?? $message['description'] ?? null;

        return $body;
    }

    public function testAGuestWritesItsOwnAddress(): void
    {
        $this->enableGuestCheckout();

        [, $guest] = $this->registerGuest();

        $response = $this->jsonRequest(
            'POST',
            '/api/front/guest/addresses',
            $this->addressPayload($this->createFixtureFactory()->customerTitle()),
            $guest['token'],
        );

        self::assertJsonResponseSuccessful($response);

        $created = AddressQuery::create()->findPk(
            json_decode((string) $response->getContent(), true)['id'],
            $this->getPropelConnection(),
        );

        self::assertNotNull($created);
        self::assertSame(
            $guest['id'],
            $created->getCustomerId(),
            'The address a guest writes belongs to the guest account holding the token.',
        );
    }

    public function testTheBodyCannotFileAGuestAddressUnderAnotherCustomer(): void
    {
        $this->enableGuestCheckout();

        $factory = $this->createFixtureFactory();
        $title = $factory->customerTitle();
        $victim = $factory->customer($title);

        [, $guest] = $this->registerGuest();

        $payload = $this->addressPayload($title);
        $payload['customer'] = '/api/admin/customers/'.$victim->getId();

        $this->jsonRequest('POST', '/api/front/guest/addresses', $payload, $guest['token']);

        self::assertSame(
            0,
            AddressQuery::create()->filterByCustomerId($victim->getId())->count($this->getPropelConnection()),
            'The owner of an address is the token, never the body.',
        );
    }

    public function testAnAnonymousCallerCannotWriteAGuestAddress(): void
    {
        $response = $this->jsonRequest(
            'POST',
            '/api/front/guest/addresses',
            $this->addressPayload($this->createFixtureFactory()->customerTitle()),
        );

        self::assertContains(
            $response->getStatusCode(),
            [401, 403],
            'Writing an address takes a guest token: without one there is no account to write it under.',
        );
    }

    public function testAGuestCannotReachTheAddressBookOfAnotherGuest(): void
    {
        $this->enableGuestCheckout();

        $factory = $this->createFixtureFactory();
        [, $victim] = $this->registerGuestInAFreshSession();
        $victimAddress = $factory->address(
            \Thelia\Model\CustomerQuery::create()->findPk($victim['id'], $this->getPropelConnection()),
        );

        [, $attacker] = $this->registerGuestInAFreshSession();

        $response = $this->jsonRequest(
            'GET',
            '/api/front/account/addresses/'.$victimAddress->getId(),
            token: $attacker['token'],
        );

        self::assertSame(
            403,
            $response->getStatusCode(),
            'The account address book stays behind ROLE_CUSTOMER: a guest token never opens it.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(CustomerTitle $title): array
    {
        $country = CountryQuery::create()->findOneByIsoalpha3('FRA');
        self::assertNotNull($country);

        return [
            'label' => 'Home',
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => '1 Main Street',
            'city' => 'Paris',
            'zipcode' => '75001',
            'country' => '/api/front/countries/'.$country->getId(),
            'customerTitle' => '/api/admin/customer_titles/'.$title->getId(),
        ];
    }
}
