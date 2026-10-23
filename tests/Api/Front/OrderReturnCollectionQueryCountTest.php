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

use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnReason;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * Every member of the customer's return list carries the reference of its
 * order. Read order by order, that is one query more per return on the page.
 */
final class OrderReturnCollectionQueryCountTest extends ApiTestCase
{
    use ForgetsPooledModels;
    use RecordsSqlQueries;

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');
    }

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testTheReturnListReadsTheOrdersOnceWhateverTheNumberOfReturns(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $references = [];

        for ($index = 0; $index < 3; ++$index) {
            $return = $this->returnFor($customer);
            $references[(int) $return->getId()] = (string) $return->getOrder()->getRef();
        }

        $token = $this->authenticateAsCustomer($customer);

        $response = null;
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$response, $token): void {
            $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $token);
        });

        self::assertJsonResponseSuccessful($response);

        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertCount(3, $members);

        foreach ($members as $member) {
            self::assertSame($references[$member['id']] ?? null, $member['orderRef'] ?? null, 'Each return must still name its own order.');
        }

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'order'),
            'The orders of the page must be read in one statement, not one by one.',
        );
    }

    /**
     * Each member of the list also carries its status - the front card reads
     * orderReturnStatus.code and its translated label. Three different
     * statuses rule out the instance pool quietly hiding a query per row
     * behind rows that all happen to point at the same one.
     */
    public function testTheReturnListReadsTheStatusOnceWhateverTheNumberOfReturns(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->returnFor($customer, OrderReturnStatus::CODE_REQUESTED);
        $this->returnFor($customer, OrderReturnStatus::CODE_ACCEPTED);
        $this->returnFor($customer, OrderReturnStatus::CODE_REFUSED);

        $token = $this->authenticateAsCustomer($customer);

        $response = null;
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$response, $token): void {
            $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $token);
        });

        self::assertJsonResponseSuccessful($response);

        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertCount(3, $members);

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'order_return_status'),
            'The statuses of the page must be read in one statement, not one by one.',
        );

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'order_return_status_i18n'),
            'The status translations of the page must be read in one statement, not one by one.',
        );
    }

    /**
     * The reason is read the same way, per member, whenever the return names
     * one - a distinct reason on each return rules out the instance pool
     * hiding the same N+1 behind rows that share one target.
     */
    public function testTheReturnListReadsTheReasonOnceWhateverTheNumberOfReturns(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->returnWithReasonFor($customer, 'A reason');
        $this->returnWithReasonFor($customer, 'Another reason');
        $this->returnWithReasonFor($customer, 'Yet another reason');

        $token = $this->authenticateAsCustomer($customer);

        $response = null;
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$response, $token): void {
            $response = $this->jsonRequest('GET', '/api/front/account/order_returns', token: $token);
        });

        self::assertJsonResponseSuccessful($response);

        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertCount(3, $members);

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'order_return_reason'),
            'The reasons of the page must be read in one statement, not one by one.',
        );

        self::assertSame(
            1,
            self::countSqlQueriesSelectingFrom($statements, 'order_return_reason_i18n'),
            'The reason translations of the page must be read in one statement, not one by one.',
        );
    }

    /**
     * The administration list reads the same relations per member: the order,
     * the status and the reason each come in one statement for the page, with
     * a distinct one on every return.
     */
    public function testTheAdminReturnListReadsTheOrdersStatusesAndReasonsOnce(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $this->returnWithReasonFor($customer, 'A reason', OrderReturnStatus::CODE_REQUESTED);
        $this->returnWithReasonFor($customer, 'Another reason', OrderReturnStatus::CODE_ACCEPTED);
        $this->returnWithReasonFor($customer, 'Yet another reason', OrderReturnStatus::CODE_REFUSED);

        $token = $this->authenticateAsAdmin();

        $response = null;
        $statements = $this->recordSqlQueriesWithoutPooledModels(function () use (&$response, $token): void {
            $response = $this->jsonRequest('GET', '/api/admin/order_returns', token: $token);
        });

        self::assertJsonResponseSuccessful($response);

        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertCount(3, $members);

        foreach (['order', 'order_return_status', 'order_return_status_i18n', 'order_return_reason', 'order_return_reason_i18n'] as $table) {
            self::assertSame(
                1,
                self::countSqlQueriesSelectingFrom($statements, $table),
                \sprintf('The %s rows of the admin page must be read in one statement, not one by one.', $table),
            );
        }
    }

    /**
     * The order filter joins the same relation: joined twice, the list must
     * still be filtered and still carry the right order.
     */
    public function testTheOrderFilterStillWorksWithTheOrdersJoined(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->returnFor($customer);
        $wanted = $this->returnFor($customer);
        $wantedRef = (string) $wanted->getOrder()->getRef();

        $token = $this->authenticateAsCustomer($customer);
        $response = $this->jsonRequest('GET', '/api/front/account/order_returns?order.ref='.urlencode($wantedRef), token: $token);
        self::assertJsonResponseSuccessful($response);

        $members = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR)['hydra:member'] ?? [];
        self::assertCount(1, $members);
        self::assertSame($wanted->getId(), $members[0]['id'] ?? null);
        self::assertSame($wantedRef, $members[0]['orderRef'] ?? null);
    }

    private function returnFor(Customer $customer, string $statusCode = OrderReturnStatus::CODE_REQUESTED): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode($statusCode));
        $return->save($this->getPropelConnection());

        return $return;
    }

    private function returnWithReasonFor(Customer $customer, string $reasonTitle, string $statusCode = OrderReturnStatus::CODE_REQUESTED): OrderReturn
    {
        $reason = (new OrderReturnReason())
            ->setCode('reason-'.uniqid())
            ->setVisible(true)
            ->setPosition(1);
        $reason->setLocale('en_US')->setTitle($reasonTitle);
        $reason->save($this->getPropelConnection());

        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus(OrderReturnStatusQuery::create()->findOneByCode($statusCode))
            ->setOrderReturnReason($reason);
        $return->save($this->getPropelConnection());

        return $return;
    }
}
