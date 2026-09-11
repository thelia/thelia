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

namespace Thelia\Tests\Api;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct as OrderProductModel;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnReason;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;

/**
 * The return API keeps every customer to their own returns, refuses a customer
 * token on the administration operations, and lets an authorized administrator
 * drive the return through its cycle.
 */
final class OrderReturnApiTest extends ApiTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        // The whole returns surface is off on a shop that did not turn the
        // feature on, so every case but the one testing that has to turn it on.
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
    }

    protected function tearDown(): void
    {
        // ConfigQuery keeps a static cache the transaction rollback cannot reach.
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testACustomerOnlySeesTheirOwnReturns(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();

        $return = $this->returnFor($owner, OrderReturnStatus::CODE_REQUESTED);

        $ownerToken = $this->authenticateAsCustomer($owner);
        $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $ownerToken);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(1, $response);

        $strangerToken = $this->authenticateAsCustomer($stranger);
        $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $strangerToken);
        self::assertJsonResponseSuccessful($response);
        self::assertHydraTotalItems(0, $response);

        self::assertSame(
            $return->getId(),
            $this->decodeMembers($this->jsonRequest('GET', '/api/front/account/order_returns', token: $ownerToken))[0]['id'] ?? null,
            'The one item the owner sees has to be their own return.',
        );
    }

    /**
     * The ownership guard is what has to answer here, and a 404 is also what a
     * misspelt uriTemplate or an unregistered resource answers: the owner
     * reading the same URL proves the route exists before the stranger is
     * turned away from it.
     */
    public function testACustomerCannotReadAnotherCustomersReturn(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();

        $return = $this->returnFor($owner, OrderReturnStatus::CODE_REQUESTED);
        $uri = '/api/front/account/order_returns/'.$return->getId();

        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', $uri, token: $this->authenticateAsCustomer($owner)),
        );

        $token = $this->authenticateAsCustomer($stranger);

        self::assertSame(403, $this->jsonRequest('GET', $uri, token: $token)->getStatusCode());
    }

    public function testAClientTokenIsRejectedOnTheAdminTransition(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);
        $uri = '/api/admin/order_returns/'.$return->getId().'/transition';
        $payload = ['statusCode' => OrderReturnStatus::CODE_RECEIVED];

        $token = $this->authenticateAsCustomer($customer);
        self::assertSame(403, $this->jsonRequest('POST', $uri, $payload, token: $token)->getStatusCode());

        // The same call with an administrator token goes through, so the 403
        // above is the firewall refusing a customer and not a dead route.
        self::assertJsonResponseSuccessful(
            $this->jsonRequest('POST', $uri, $payload, token: $this->authenticateAsAdmin()),
        );
    }

    /**
     * The whole /api/admin surface only asks the firewall for ROLE_ADMIN, which
     * every administrator holds. What each one may reach is decided by their
     * profile, and every other case here logs in as a superadministrator - which
     * short-circuits that check entirely.
     */
    public function testAnAdminWithoutTheReturnsPermissionIsRefused(): void
    {
        $customer = $this->customer();
        $return = $this->returnWithLine($customer, OrderReturnStatus::CODE_ACCEPTED);
        $line = $return->getOrderReturnLines()->getFirst();

        $admin = $this->factory->restrictedAdmin([AdminResources::ORDER => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($admin);

        $refused = [
            'collection' => ['GET', '/api/admin/order_returns', []],
            'item' => ['GET', '/api/admin/order_returns/'.$return->getId(), []],
            'transition' => ['POST', '/api/admin/order_returns/'.$return->getId().'/transition', ['statusCode' => OrderReturnStatus::CODE_RECEIVED]],
            'deletion' => ['DELETE', '/api/admin/order_returns/'.$return->getId(), []],
            'line collection' => ['GET', '/api/admin/order_return_lines', []],
            'line item' => ['GET', '/api/admin/order_return_lines/'.$line->getId(), []],
        ];

        foreach ($refused as $label => [$method, $uri, $payload]) {
            self::assertSame(
                403,
                $this->jsonRequest($method, $uri, $payload, token: $token)->getStatusCode(),
                \sprintf('An admin with no returns permission reached the %s.', $label),
            );
        }

        self::assertSame(
            403,
            $this->jsonRequest('PATCH', '/api/admin/order_return_lines/'.$line->getId(), ['quantityReceived' => 1.0], $token, 'merge-patch+json')->getStatusCode(),
            'An admin with no returns permission patched a return line.',
        );
    }

    /**
     * The counterpart: the permission is what was missing, not the token. An
     * administrator granted `admin.order-return` goes through the same calls.
     */
    public function testAnAdminGrantedTheReturnsPermissionIsAllowed(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);

        $admin = $this->factory->restrictedAdmin([
            AdminResources::ORDER_RETURN => [AccessManager::VIEW, AccessManager::UPDATE],
        ]);
        $token = $this->authenticateAsAdmin($admin);

        self::assertJsonResponseSuccessful($this->jsonRequest('GET', '/api/admin/order_returns', token: $token));
        self::assertJsonResponseSuccessful(
            $this->jsonRequest(
                'POST',
                '/api/admin/order_returns/'.$return->getId().'/transition',
                ['statusCode' => OrderReturnStatus::CODE_RECEIVED],
                token: $token,
            ),
        );
    }

    public function testAnAdminMovesAReturnThroughItsCycle(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_RECEIVED],
            token: $token,
        );

        self::assertJsonResponseSuccessful($response);

        $received = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_RECEIVED);
        $return->reload(false, $this->getPropelConnection());
        self::assertSame((int) $received?->getId(), (int) $return->getStatusId());
    }

    public function testAnAdminCannotApplyAnIllegalTransition(): void
    {
        $customer = $this->customer();
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_REQUESTED);

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns/'.$return->getId().'/transition',
            ['statusCode' => OrderReturnStatus::CODE_SETTLED],
            token: $token,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testACustomerOpensAReturnOnTheirOwnOrder(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');

        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->findOne($this->getPropelConnection());

        self::assertNotNull($created);
        self::assertFalse((bool) $created->getCreatedByAdmin());
        self::assertSame(
            (int) OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_REQUESTED),
            (int) $created->getStatusId(),
        );
        self::assertSame(10.0, (float) $created->getRefundAmount());
    }

    public function testAnAdminOpensAReturnOnBehalfOfACustomer(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->findOne($this->getPropelConnection());

        self::assertNotNull($created);
        self::assertTrue((bool) $created->getCreatedByAdmin());
    }

    /**
     * A shop that turns returns off after having collected some must stop
     * serving them everywhere, not just stop accepting new ones: recette point
     * 9 of the story asks for nothing to appear on either side.
     */
    public function testNothingOfTheReturnsSurfaceAnswersWhenTheFeatureIsDisabled(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);
        $return = $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);
        $reason = $this->reason(visible: true);

        $customerToken = $this->authenticateAsCustomer($customer);
        $adminToken = $this->authenticateAsAdmin();

        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '0');
        ConfigQuery::resetCache();

        $silenced = [
            'front collection' => ['GET', '/api/front/account/order_returns', [], $customerToken],
            'front item' => ['GET', '/api/front/account/order_returns/'.$return->getId(), [], $customerToken],
            'front reasons' => ['GET', '/api/front/account/order_return_reasons', [], $customerToken],
            'front reason' => ['GET', '/api/front/account/order_return_reasons/'.$reason->getId(), [], $customerToken],
            'front statuses' => ['GET', '/api/front/account/order_return_statutes', [], $customerToken],
            'front creation' => ['POST', '/api/front/account/order_returns', [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ], $customerToken],
            'admin collection' => ['GET', '/api/admin/order_returns', [], $adminToken],
            'admin item' => ['GET', '/api/admin/order_returns/'.$return->getId(), [], $adminToken],
            'admin reasons' => ['GET', '/api/admin/order_return_reasons', [], $adminToken],
            'admin transition' => ['POST', '/api/admin/order_returns/'.$return->getId().'/transition', [
                'statusCode' => OrderReturnStatus::CODE_RECEIVED,
            ], $adminToken],
            'admin deletion' => ['DELETE', '/api/admin/order_returns/'.$return->getId(), [], $adminToken],
        ];

        foreach ($silenced as $label => [$method, $uri, $payload, $token]) {
            self::assertSame(
                404,
                $this->jsonRequest($method, $uri, $payload, token: $token)->getStatusCode(),
                \sprintf('The %s still answers on a shop where returns are disabled.', $label),
            );
        }
    }

    /**
     * The reasons and the statuses are what a customer picks from when opening
     * a return, so they used to sit outside `^/api/front/account` - the pattern
     * the kernel asks ROLE_CUSTOMER on - and were served to anyone at all.
     */
    public function testTheFrontReferenceEndpointsAreNotPublic(): void
    {
        $this->reason(visible: true);

        // The paths outside the authenticated prefix must be gone, not merely
        // duplicated: left in place they would keep serving the reference data
        // to anyone.
        foreach (['/api/front/order_return_reasons', '/api/front/order_return_statutes'] as $uri) {
            self::assertSame(
                404,
                $this->jsonRequest('GET', $uri)->getStatusCode(),
                \sprintf('%s is still served outside the authenticated prefix.', $uri),
            );
        }

        foreach (['/api/front/account/order_return_reasons', '/api/front/account/order_return_statutes'] as $uri) {
            self::assertSame(
                401,
                $this->jsonRequest('GET', $uri)->getStatusCode(),
                \sprintf('%s answers without a token.', $uri),
            );
        }

        $token = $this->authenticateAsCustomer($this->customer());

        foreach (['/api/front/account/order_return_reasons', '/api/front/account/order_return_statutes'] as $uri) {
            self::assertJsonResponseSuccessful($this->jsonRequest('GET', $uri, token: $token));
        }
    }

    /**
     * `visible` is the merchant's choice, not the caller's: a reason retired
     * from the list must not come back by dropping the filter, by asking for
     * the invisible ones, or by reading the reason by its id.
     */
    public function testAReasonTheMerchantHidesIsNeverServedOnTheFront(): void
    {
        $shown = $this->reason(visible: true);
        $hidden = $this->reason(visible: false);

        $token = $this->authenticateAsCustomer($this->customer());

        foreach (['', '?visible=false', '?visible=0'] as $queryString) {
            $response = $this->jsonRequest('GET', '/api/front/account/order_return_reasons'.$queryString, token: $token);
            self::assertJsonResponseSuccessful($response);

            $codes = array_column($this->decodeMembers($response), 'code');
            self::assertNotContains((string) $hidden->getCode(), $codes, \sprintf('The hidden reason is served on "%s".', $queryString));
        }

        self::assertSame(
            404,
            $this->jsonRequest('GET', '/api/front/account/order_return_reasons/'.$hidden->getId(), token: $token)->getStatusCode(),
        );
        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', '/api/front/account/order_return_reasons/'.$shown->getId(), token: $token),
        );
    }

    /**
     * The gate reads what other returns already hold from the database, where
     * the lines of the request being checked are not written yet. One ordered
     * unit split over several lines used to pass the gate once per line.
     */
    public function testSeveralLinesOnTheSameOrderProductCannotExceedTheOrderedQuantity(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $line = ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0];

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => array_fill(0, 10, $line),
            ],
            token: $token,
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('exceeds the returnable quantity', (string) $response->getContent());

        self::assertSame(
            0,
            OrderReturnQuery::create()->filterByCustomerId((int) $customer->getId())->count($this->getPropelConnection()),
            'A return was written for ten times the ordered quantity.',
        );
    }

    public function testACustomerCannotReturnMoreThanTheOrderedQuantity(): void
    {
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');

        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 5.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(422, $response->getStatusCode());

        // A 422 alone tells the customer nothing: the story asks for a business
        // error they can read, so the reason has to reach the body.
        self::assertStringContainsString(
            'exceeds the returnable quantity',
            (string) $response->getContent(),
            'The refusal carries no readable reason.',
        );
    }

    /**
     * `quantityReceived` is what the reception restocks and what the refund is
     * recomputed on. The admin patch exposed both it and `quantity` with no
     * check at all, so a merchant typing 20 instead of 2 inflated the stock and
     * the refund by the difference.
     */
    public function testAnAdminCannotRecordMoreReceivedThanTheCustomerAskedFor(): void
    {
        $customer = $this->customer();
        $return = $this->returnWithLine($customer, OrderReturnStatus::CODE_ACCEPTED, quantity: 2.0);
        $line = $return->getOrderReturnLines()->getFirst();

        $token = $this->authenticateAsAdmin();
        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/order_return_lines/'.$line->getId(),
            ['quantityReceived' => 20.0],
            $token,
            'merge-patch+json',
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('received quantity', (string) $response->getContent());

        $line->reload(false, $this->getPropelConnection());
        self::assertSame(0.0, (float) $line->getQuantityReceived(), 'The excessive received quantity was written anyway.');
    }

    public function testAnAdminCanRecordUpToTheRequestedQuantity(): void
    {
        $customer = $this->customer();
        $return = $this->returnWithLine($customer, OrderReturnStatus::CODE_ACCEPTED, quantity: 2.0);
        $line = $return->getOrderReturnLines()->getFirst();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/order_return_lines/'.$line->getId(),
            ['quantityReceived' => 2.0],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertJsonResponseSuccessful($response);

        $line->reload(false, $this->getPropelConnection());
        self::assertSame(2.0, (float) $line->getQuantityReceived());
    }

    /**
     * Raising `quantity` on an existing line is opening a bigger return, and it
     * has to pass the same gate the creation does - the ordered quantity of the
     * line included.
     */
    public function testAnAdminCannotRaiseALineAboveTheOrderedQuantity(): void
    {
        $customer = $this->customer();
        $return = $this->returnWithLine($customer, OrderReturnStatus::CODE_ACCEPTED, quantity: 1.0);
        $line = $return->getOrderReturnLines()->getFirst();

        $response = $this->jsonRequest(
            'PATCH',
            '/api/admin/order_return_lines/'.$line->getId(),
            ['quantity' => 9.0],
            $this->authenticateAsAdmin(),
            'merge-patch+json',
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('exceeds the returnable quantity', (string) $response->getContent());

        $line->reload(false, $this->getPropelConnection());
        self::assertSame(1.0, (float) $line->getQuantity());
    }

    /**
     * The rate limiter is the shop's protection against a flood of requests,
     * not a punishment for getting a form wrong: consuming a token before the
     * eligibility check let twenty refused attempts close the hour for the
     * customer's one valid return.
     */
    public function testRefusedRequestsDoNotEatTheQuotaOfAValidOne(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);
        $token = $this->authenticateAsCustomer($customer);

        $body = static fn (float $quantity): array => [
            'order' => '/api/front/account/orders/'.$order->getId(),
            'orderReturnLines' => [
                ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => $quantity],
            ],
        ];

        // The quota is twenty requests an hour: exactly enough refused attempts
        // to use it up if a refusal counted.
        for ($attempt = 1; $attempt <= 20; ++$attempt) {
            self::assertSame(
                422,
                $this->jsonRequest('POST', '/api/front/account/order_returns', $body(9.0), token: $token)->getStatusCode(),
                \sprintf('Attempt %d was expected to be refused on its quantity.', $attempt),
            );
        }

        self::assertSame(
            201,
            $this->jsonRequest('POST', '/api/front/account/order_returns', $body(1.0), token: $token)->getStatusCode(),
            'Twenty refused attempts closed the hour for a legitimate return.',
        );
    }

    /**
     * The counterpart of the case above: moving the quota behind the
     * eligibility gate must not disarm it. Twenty returns opened for real do
     * use the hour up.
     */
    public function testTheHourlyQuotaStillClosesAfterTwentyReturns(): void
    {
        $customer = $this->customer();
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProductFor($order, 21.0);
        $token = $this->authenticateAsCustomer($customer);

        $payload = [
            'order' => '/api/front/account/orders/'.$order->getId(),
            'orderReturnLines' => [
                ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
            ],
        ];

        for ($opened = 1; $opened <= 20; ++$opened) {
            self::assertSame(
                201,
                $this->jsonRequest('POST', '/api/front/account/order_returns', $payload, token: $token)->getStatusCode(),
                \sprintf('Return %d was expected to be accepted.', $opened),
            );
        }

        self::assertSame(
            429,
            $this->jsonRequest('POST', '/api/front/account/order_returns', $payload, token: $token)->getStatusCode(),
            'The twenty-first return of the hour was accepted: the quota is no longer enforced.',
        );
    }

    /**
     * A merchant retiring a reason must not take the returns that named it down
     * with them: the reason is nulled and the wording stays on the return as the
     * snapshot taken when it was opened.
     */
    public function testDeletingAReasonLeavesTheExistingReturnsReadable(): void
    {
        $customer = $this->customer();
        [$order, $orderProduct] = $this->paidOrderWithProduct($customer);
        $reason = $this->reason(visible: true);

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnReason' => '/api/front/account/order_return_reasons/'.$reason->getId(),
                'orderReturnLines' => [
                    ['orderProduct' => '/api/front/account/order_products/'.$orderProduct->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );

        self::assertSame(201, $response->getStatusCode());

        $created = OrderReturnQuery::create()
            ->filterByCustomerId((int) $customer->getId())
            ->findOne($this->getPropelConnection());
        self::assertNotNull($created);
        self::assertSame((int) $reason->getId(), (int) $created->getReasonId());
        // The snapshot is taken in the language of the order, which the test
        // database serves first: fr_FR.
        self::assertSame('Un motif', $created->getReasonTitle());

        $reason->delete($this->getPropelConnection());

        $created->reload(false, $this->getPropelConnection());
        self::assertNull($created->getReasonId(), 'The return still points at a reason that no longer exists.');
        self::assertSame('Un motif', $created->getReasonTitle(), 'The wording the customer chose is gone with the reason.');

        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', '/api/front/account/order_returns/'.$created->getId(), token: $token),
        );
    }

    public function testAStatusIsReadableByIdAndByCode(): void
    {
        $token = $this->authenticateAsAdmin();
        $requested = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED);
        self::assertNotNull($requested);

        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', '/api/admin/order_return_statutes/'.$requested->getId(), token: $token),
        );
        self::assertJsonResponseSuccessful(
            $this->jsonRequest('GET', '/api/admin/order_return_statutes/code/'.OrderReturnStatus::CODE_REQUESTED, token: $token),
        );
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }

    /**
     * A reason as the install seeds them: translated in every language of the
     * shop, because the snapshot kept on a return is taken in the language of
     * the order.
     */
    private function reason(bool $visible): OrderReturnReason
    {
        $reason = (new OrderReturnReason())
            ->setCode('reason-'.uniqid())
            ->setVisible($visible)
            ->setPosition(1);
        $reason->setLocale('en_US')->setTitle('A reason');
        $reason->setLocale('fr_FR')->setTitle('Un motif');
        $reason->save($this->getPropelConnection());

        return $reason;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeMembers(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        return $decoded['member'] ?? $decoded['hydra:member'] ?? [];
    }

    /**
     * A return that already holds one line, as the administration screens see
     * it: the line is what the merchant patches on reception.
     */
    private function returnWithLine(Customer $customer, string $statusCode, float $quantity = 1.0): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $orderProduct = $this->orderProductFor($order, $quantity);

        $status = OrderReturnStatusQuery::create()->findOneByCode($statusCode);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status);
        $return->save($this->getPropelConnection());

        $line = (new OrderReturnLine())
            ->setOrderReturn($return)
            ->setOrderProduct($orderProduct)
            ->setProductSaleElementsId($orderProduct->getProductSaleElementsId())
            ->setQuantity($quantity)
            ->setQuantityReceived(0.0);
        $line->save($this->getPropelConnection());

        $return->clearOrderReturnLines();

        return $return;
    }

    private function returnFor(Customer $customer, string $statusCode): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->orderProductFor($order);

        $status = OrderReturnStatusQuery::create()->findOneByCode($statusCode);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status);
        $return->save($this->getPropelConnection());

        return $return;
    }

    /**
     * @return array{0: Order, 1: OrderProductModel}
     */
    private function paidOrderWithProduct(Customer $customer): array
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        return [$order, $this->orderProductFor($order)];
    }

    private function orderProductFor(Order $order, float $quantity = 1.0): OrderProductModel
    {
        $orderProduct = (new OrderProductModel())
            ->setOrderId((int) $order->getId())
            ->setProductRef('REF-'.uniqid())
            ->setProductSaleElementsRef('PSE-'.uniqid())
            ->setProductSaleElementsId(1)
            ->setTitle('A returnable product')
            ->setQuantity($quantity)
            ->setPrice('10.000000')
            ->setPromoPrice('10.000000')
            ->setWasNew(1)
            ->setWasInPromo(0)
            ->setVirtual(0);
        $orderProduct->save($this->getPropelConnection());

        return $orderProduct;
    }
}
