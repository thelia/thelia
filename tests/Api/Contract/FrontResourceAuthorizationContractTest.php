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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Model\Currency;
use Thelia\Model\CustomerList;
use Thelia\Model\Quote;
use Thelia\Model\QuoteRevision;
use Thelia\Test\ApiTestCase;

/**
 * The front API has no default-deny: a resource is anonymous unless it names a
 * security rule. Two front resources used to hand sensitive data to any caller —
 * coupon codes (a secret distributed out of band) and the exact version of every
 * installed module (a fingerprint for targeting known CVEs). This contract keeps
 * them shut and fails loudly if a regression reopens either door.
 */
final class FrontResourceAuthorizationContractTest extends ApiTestCase
{
    public function testFrontCouponsRejectAnonymousAccess(): void
    {
        $this->createFixtureFactory()->coupon();

        $response = $this->jsonRequest('GET', '/api/front/coupons');

        self::assertSame(404, $response->getStatusCode(), 'Anonymous callers must not read coupon codes.');
    }

    public function testFrontCouponsAreNotExposedToAuthenticatedCustomer(): void
    {
        $this->createFixtureFactory()->coupon();

        $response = $this->jsonRequest('GET', '/api/front/coupons', token: $this->authenticateAsCustomer());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFrontModulesAreNotExposed(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/modules');

        self::assertSame(404, $response->getStatusCode(), 'The module list (and its versions) must not be reachable from the front API.');
    }

    /**
     * The checkout of an account is six operations that read a cart, change what an order
     * will be built from, and place it. Not one of them is anonymous, and this is the
     * contract that says so operation by operation rather than trusting a path prefix to
     * stay what it is.
     */
    #[DataProvider('checkoutOperations')]
    public function testCheckoutOperationsRejectAnonymousAccess(string $method, string $path): void
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart($factory->customer($factory->customerTitle()));

        $response = $this->jsonRequest($method, '/api/front/account/checkout/'.$cart->getId().'/'.$path);

        self::assertSame(401, $response->getStatusCode(), \sprintf('%s /front/account/checkout/{cartId}/%s must demand an account.', $method, $path));
    }

    /**
     * Ordering by reference answers with titles, prices and stock levels for up to five
     * hundred lines: an anonymous caller must not reach either operation.
     */
    #[DataProvider('quickOrderOperations')]
    public function testQuickOrderOperationsRejectAnonymousAccess(string $path): void
    {
        $factory = $this->createFixtureFactory();
        $cart = $factory->cart($factory->customer($factory->customerTitle()));

        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/quick-order/'.str_replace('{cartId}', (string) $cart->getId(), $path),
            ['lines' => [['reference' => 'ANY', 'quantity' => 1]]],
            format: 'json',
        );

        self::assertSame(401, $response->getStatusCode(), \sprintf('POST /front/account/quick-order/%s must demand an account.', $path));
    }

    /**
     * A purchase list holds what an account buys and in what quantities, and loading one
     * answers with prices and stock levels: every operation demands an account, stated
     * on the operation rather than left to the path prefix.
     */
    #[DataProvider('purchaseListOperations')]
    public function testPurchaseListOperationsRejectAnonymousAccess(string $method, string $path, string $format): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle());
        $cart = $factory->cart($customer);
        $order = $factory->order($customer);
        $list = (new CustomerList())->setCustomerId($customer->getId())->setTitle('Somebody\'s list');
        $list->save($this->getPropelConnection());

        $response = $this->jsonRequest(
            $method,
            '/api/front/account/purchase-lists'.strtr($path, ['{id}' => (string) $list->getId(), '{cartId}' => (string) $cart->getId(), '{orderId}' => (string) $order->getId()]),
            ['title' => 'Anonymous', 'lines' => [['reference' => 'ANY', 'quantity' => 1]]],
            format: $format,
        );

        self::assertSame(401, $response->getStatusCode(), \sprintf('%s /front/account/purchase-lists%s must demand an account.', $method, $path));
    }

    /**
     * A company holds who buys for it, with what limits, and whom it invited: every operation that
     * reads or writes any of it demands an account, stated on the operation rather than left to the
     * path prefix, and an account of nobody's company is refused as well.
     */
    #[DataProvider('companyOperations')]
    public function testCompanyOperationsRejectAnonymousAccess(string $method, string $path, string $format): void
    {
        $response = $this->jsonRequest($method, '/api/front/account/company'.str_replace('{id}', '1', $path), ['name' => 'Anonymous', 'token' => 'x', 'email' => 'a@b.test', 'roleCode' => 'buyer'], format: $format);

        self::assertSame(401, $response->getStatusCode(), \sprintf('%s /front/account/company%s must demand an account.', $method, $path));
    }

    #[DataProvider('companyOperations')]
    public function testCompanyOperationsRefuseAnAccountOfNoCompany(string $method, string $path, string $format): void
    {
        $factory = $this->createFixtureFactory();
        $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);

        $response = $this->jsonRequest(
            $method,
            '/api/front/account/company'.str_replace('{id}', '1', $path),
            ['name' => 'Mine', 'token' => 'x', 'email' => 'a@b.test', 'roleCode' => 'buyer'],
            $this->authenticateAsCustomer($customer),
            $format,
        );

        // Accepting an invitation is what an account of no company does: a token that is no token is a 422, not a 2xx.
        self::assertContains($response->getStatusCode(), [403, 404, 422], \sprintf('%s /front/account/company%s must not answer an account of no company.', $method, $path));
    }

    /**
     * The quotes of the account, their revisions and the answers to them: an account only.
     */
    #[DataProvider('quoteOperations')]
    public function testQuoteOperationsRejectAnonymousAccess(string $method, string $path, string $format): void
    {
        $response = $this->jsonRequest($method, '/api/front/account/quotes'.str_replace('{id}', '1', $path), ['cartId' => 1, 'revision' => 1, 'lines' => []], format: $format);

        self::assertSame(401, $response->getStatusCode(), \sprintf('%s /front/account/quotes%s must demand an account.', $method, $path));
    }

    /**
     * A quote that exists answers "not found" to an account it does not belong to, on every
     * operation: the answer of a number that was never issued.
     */
    #[DataProvider('quoteOperationsOnAQuote')]
    public function testQuoteOperationsAnswerNotFoundToAnAccountThatDoesNotOwnTheQuote(string $method, string $path, string $format): void
    {
        $factory = $this->createFixtureFactory();
        $owner = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $other = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $quote = (new Quote())
            ->setRef('CONTRACT'.bin2hex(random_bytes(4)))
            ->setCustomerId($owner->getId())
            ->setState('quoted')
            ->setCurrentRevision(1)
            ->setCurrencyId(Currency::getDefaultCurrency()->getId());
        $quote->save($this->getPropelConnection());
        (new QuoteRevision())
            ->setQuoteId($quote->getId())
            ->setNumber(1)
            ->setAuthor('customer')
            ->setState('sent')
            ->setDiscount('0.000000')
            ->setPostage('0.000000')
            ->save($this->getPropelConnection());
        $uri = '/api/front/account/quotes'.str_replace('{id}', (string) $quote->getId(), $path);
        $body = ['cartId' => 1, 'revision' => 1, 'lines' => []];

        self::assertContains($this->jsonRequest($method, $uri, $body, $this->authenticateAsCustomer($owner), $format)->getStatusCode(), [200, 422, 409], 'The owner reaches it.');
        self::assertSame(404, $this->jsonRequest($method, $uri, $body, $this->authenticateAsCustomer($other), $format)->getStatusCode(), \sprintf('%s %s must answer "not found" to an account that does not own the quote.', $method, $uri));
    }

    /**
     * The list of quotes is bounded to the caller, not only each quote read by its number.
     */
    public function testTheListOfQuotesHoldsTheQuotesOfTheCallerOnly(): void
    {
        $factory = $this->createFixtureFactory();
        $owner = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $other = $factory->customer($factory->customerTitle(), ['password' => 'password']);
        $quote = (new Quote())
            ->setRef('CONTRACT'.bin2hex(random_bytes(4)))
            ->setCustomerId($owner->getId())
            ->setState('quoted')
            ->setCurrentRevision(1)
            ->setCurrencyId(Currency::getDefaultCurrency()->getId());
        $quote->save($this->getPropelConnection());

        $owned = json_decode((string) $this->jsonRequest('GET', '/api/front/account/quotes', token: $this->authenticateAsCustomer($owner))->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $foreign = json_decode((string) $this->jsonRequest('GET', '/api/front/account/quotes', token: $this->authenticateAsCustomer($other))->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame([$quote->getId()], array_column($owned['hydra:member'], 'id'));
        self::assertSame([], $foreign['hydra:member']);
    }

    /**
     * The orders and the addresses of the account are read through the company as well: still an
     * account only.
     */
    #[DataProvider('accountOperations')]
    public function testOrderAndAddressOperationsRejectAnonymousAccess(string $method, string $path): void
    {
        $response = $this->jsonRequest($method, '/api/front/account/'.$path, format: 'json');

        self::assertSame(401, $response->getStatusCode(), \sprintf('%s /front/account/%s must demand an account.', $method, $path));
    }

    /**
     * The one company operation open to anybody reads an invitation, and answers a token that does
     * not stand with a 404 and no other word.
     */
    public function testTheInvitationReadIsPublicAndAnswersOnlyA404ToWhatDoesNotStand(): void
    {
        $response = $this->jsonRequest('GET', '/api/front/company-invitations/not-a-token');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringNotContainsString('Acme', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function quoteOperations(): iterable
    {
        yield 'list' => ['GET', '', 'json'];
        yield 'read' => ['GET', '/{id}', 'json'];
        yield 'revisions' => ['GET', '/{id}/revisions', 'json'];
        yield 'revision' => ['GET', '/{id}/revisions/1', 'json'];
        yield 'request' => ['POST', '', 'json'];
        yield 'counter-proposal' => ['POST', '/{id}/counter-offer', 'json'];
        yield 'acceptance' => ['POST', '/{id}/accept', 'json'];
        yield 'refusal' => ['POST', '/{id}/reject', 'json'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function quoteOperationsOnAQuote(): iterable
    {
        yield 'read' => ['GET', '/{id}', 'json'];
        yield 'revisions' => ['GET', '/{id}/revisions', 'json'];
        yield 'revision' => ['GET', '/{id}/revisions/1', 'json'];
        yield 'counter-proposal' => ['POST', '/{id}/counter-offer', 'json'];
        yield 'acceptance' => ['POST', '/{id}/accept', 'json'];
        yield 'refusal' => ['POST', '/{id}/reject', 'json'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function companyOperations(): iterable
    {
        yield 'company read' => ['GET', '', 'json'];
        yield 'company edit' => ['PATCH', '', 'merge-patch+json'];
        yield 'buyers' => ['GET', '/customers', 'json'];
        yield 'buyer read' => ['GET', '/customers/{id}', 'json'];
        yield 'buyer change' => ['PATCH', '/customers/{id}', 'merge-patch+json'];
        yield 'buyer detach' => ['DELETE', '/customers/{id}', 'json'];
        yield 'roles' => ['GET', '/roles', 'json'];
        yield 'invitation' => ['POST', '/invitations', 'json'];
        yield 'invitation accepted' => ['POST', '/invitations/accept', 'json'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function accountOperations(): iterable
    {
        yield 'orders' => ['GET', 'orders'];
        yield 'order' => ['GET', 'orders/1'];
        yield 'addresses' => ['GET', 'addresses'];
        yield 'address' => ['GET', 'addresses/1'];
        yield 'address creation' => ['POST', 'addresses'];
        yield 'address change' => ['PUT', 'addresses/1'];
        yield 'address deletion' => ['DELETE', 'addresses/1'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function purchaseListOperations(): iterable
    {
        yield 'collection' => ['GET', '', 'json'];
        yield 'read' => ['GET', '/{id}', 'json'];
        yield 'control table' => ['GET', '/{id}/table', 'json'];
        yield 'creation' => ['POST', '', 'json'];
        yield 'creation from a cart' => ['POST', '/from-cart/{cartId}', 'json'];
        yield 'creation from an order' => ['POST', '/from-order/{orderId}', 'json'];
        yield 'rename' => ['PATCH', '/{id}', 'merge-patch+json'];
        yield 'deletion' => ['DELETE', '/{id}', 'json'];
        yield 'copy' => ['POST', '/{id}/duplicate', 'json'];
        yield 'lines added' => ['POST', '/{id}/items', 'json'];
        yield 'lines replaced' => ['PUT', '/{id}/items', 'json'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function quickOrderOperations(): iterable
    {
        yield 'resolution' => ['resolve'];
        yield 'addition to the cart' => ['{cartId}/add'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function checkoutOperations(): iterable
    {
        yield 'delivery address' => ['POST', 'delivery_address'];
        yield 'invoice address' => ['POST', 'invoice_address'];
        yield 'delivery module' => ['POST', 'delivery_module'];
        yield 'payment module' => ['POST', 'payment_module'];
        yield 'validation' => ['GET', 'validation'];
        yield 'placement' => ['POST', 'place'];
    }
}
