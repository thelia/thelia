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

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Core\Event\OrderReturn\OrderReturnEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Domain\OrderReturn\Service\ReturnEligibilityChecker;
use Thelia\Model\Admin;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderReturnLineTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnLine;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\ApiTestCase;
use Thelia\Test\FixtureFactory;
use Thelia\Test\Trait\ForgetsPooledModels;
use Thelia\Test\Trait\RecordsSqlQueries;
use Thelia\Tests\Integration\Domain\OrderReturn\CompetingRequestTrait;
use Thelia\Tests\Integration\Domain\OrderReturn\SecondConnectionTrait;

/**
 * Two requests opening returns on the same order at the same time, each one a
 * genuinely independent database session.
 *
 * The transaction ApiTestCase wraps around a test would make every request of
 * the test one session that never waits on itself: these tests commit their
 * fixtures for real and delete them in tearDown(). The competing request runs
 * in a process of its own (see CompetingRequestTrait), so that it can commit,
 * or ask for one more lock, while the request under test is waiting on it.
 *
 * Tagged `concurrency`: a run that cannot afford a child process and real lock
 * waits skips it with `--exclude-group concurrency`.
 */
#[Group('concurrency')]
final class OrderReturnConcurrencyTest extends ApiTestCase
{
    use CompetingRequestTrait;
    use ForgetsPooledModels;
    use RecordsSqlQueries;
    use SecondConnectionTrait;

    /** Rows enough to make the competing transaction the one a deadlock keeps. */
    private const int BALLAST_ROWS = 50;

    protected bool $useTransaction = false;

    private FixtureFactory $factory;

    private ?string $previousEnabled = null;

    private ?int $previousLockWaitTimeout = null;

    /** @var list<int> */
    private array $orderIds = [];

    /** @var list<int> */
    private array $adminIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->previousEnabled = ConfigQuery::read(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '0');
        ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, '1');

        // Long enough for the competing process to see the wait and act on it,
        // short enough for a lock nobody releases to fail the test, not hang it.
        $this->previousLockWaitTimeout = (int) $this->mainConnection()->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn();
        $this->mainConnection()->exec('SET SESSION innodb_lock_wait_timeout = '.self::COMPETING_PATIENCE_SECONDS);
    }

    protected function tearDown(): void
    {
        try {
            $this->stopCompetingRequest();

            if (null !== $this->previousLockWaitTimeout) {
                $this->mainConnection()->exec('SET SESSION innodb_lock_wait_timeout = '.$this->previousLockWaitTimeout);
            }

            $this->deleteTheCommittedOrders($this->orderIds);

            foreach ($this->adminIds as $adminId) {
                $this->secondConnection()->prepare('DELETE FROM admin WHERE id = :id')->execute([':id' => $adminId]);
            }
            $this->secondConnection = null;
        } finally {
            if (null !== $this->previousEnabled) {
                ConfigQuery::write(ReturnEligibilityChecker::ENABLED_CONFIG_KEY, $this->previousEnabled);
            }

            ConfigQuery::resetCache();
            OrderReturnStatusQuery::resetCache();

            parent::tearDown();
        }
    }

    /**
     * The first request takes the last unit of the line and commits while the
     * second one waits on its locks. The second one must then see that return,
     * and be refused the unit.
     */
    public function testTheLastUnitIsNotGrantedTwiceThroughTheFrontApi(): void
    {
        [$order, $customer, [$line]] = $this->paidOrderWithLines(1.0);
        $token = $this->authenticateAsCustomer($customer);

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $line->getId()],
            ['return', (int) $order->getId(), (int) $customer->getId(), $this->requestedStatusId(), false, [[(int) $line->getId(), 1.0]]],
        ]);

        $response = $this->openFrontReturn($token, $order, [[$line, 1.0]]);
        $competing = $this->finishCompetingRequest();

        self::assertSame(422, $response->getStatusCode(), 'The second request was granted the unit the first one took while it waited: '.$response->getContent().' / competing request: '.$competing);
        self::assertStringContainsString('exceeds the returnable quantity', (string) $response->getContent());
        self::assertSame(1.0, $this->committedReturnedQuantity($line), 'One unit ordered, one unit returned at most.');
    }

    /**
     * The same race through the ORDER_RETURN_CREATE event, the way the
     * back-office and the modules open a return.
     */
    public function testTheLastUnitIsNotGrantedTwiceThroughTheCreateEvent(): void
    {
        [$order, $customer, [$line]] = $this->paidOrderWithLines(1.0);

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $line->getId()],
            ['return', (int) $order->getId(), (int) $customer->getId(), $this->requestedStatusId(), false, [[(int) $line->getId(), 1.0]]],
        ]);

        $refusal = null;

        try {
            $this->openReturnThroughTheEvent($order, $customer, $line, 1.0);
        } catch (ReturnNotAllowedException $exception) {
            $refusal = $exception->getMessage();
        }

        $competing = $this->finishCompetingRequest();

        self::assertSame('The returned quantity exceeds the returnable quantity of this line.', $refusal, 'The create event granted the unit a competing return took while it waited. Competing request: '.$competing);
        self::assertSame(1.0, $this->committedReturnedQuantity($line), 'One unit ordered, one unit returned at most.');
    }

    /**
     * A module may open a return inside a transaction of its own, after having
     * read in it: the picture of that transaction is older than a return
     * another request committed since, and the check must still see it.
     */
    public function testAReturnOpenedInACallerTransactionThatAlreadyReadSeesWhatWasCommittedSince(): void
    {
        [$order, $customer, [$line]] = $this->paidOrderWithLines(1.0);

        $refusal = null;
        $this->mainConnection()->beginTransaction();

        try {
            OrderTableMap::clearInstancePool();
            self::assertNotNull(OrderQuery::create()->findPk($order->getId(), $this->mainConnection()));

            $this->secondConnection()->prepare('INSERT INTO order_return (order_id, customer_id, status_id, include_postage, created_at, updated_at) VALUES (:order, :customer, :status, 0, NOW(), NOW())')
                ->execute([':order' => $order->getId(), ':customer' => $customer->getId(), ':status' => $this->requestedStatusId()]);
            $this->secondConnection()->prepare('INSERT INTO order_return_line (order_return_id, order_product_id, quantity, created_at, updated_at) VALUES (:return, :line, 1, NOW(), NOW())')
                ->execute([':return' => $this->secondConnection()->lastInsertId(), ':line' => $line->getId()]);

            $this->openReturnThroughTheEvent($order, $customer, $line, 1.0);
        } catch (ReturnNotAllowedException $exception) {
            $refusal = $exception->getMessage();
        } finally {
            $this->mainConnection()->rollBack();
        }

        self::assertSame('The returned quantity exceeds the returnable quantity of this line.', $refusal, 'The unit another request committed after the caller transaction first read was granted again.');
    }

    /**
     * The postage belongs to the order, not to a line: two returns on two
     * different lines share no line, and still only one of them may carry it.
     */
    public function testThePostageIsNotCarriedByTwoReturns(): void
    {
        [$order, $customer, [$first, $second]] = $this->paidOrderWithLines(1.0, 1.0);
        $token = $this->authenticateAsAdmin($this->committedAdmin());

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $first->getId()],
            ['return', (int) $order->getId(), (int) $customer->getId(), $this->requestedStatusId(), true, [[(int) $first->getId(), 1.0]]],
        ]);

        // Only the merchant decides whether the postage goes back.
        $response = $this->jsonRequest(
            'POST',
            '/api/admin/order_returns',
            [
                'order' => '/api/admin/orders/'.$order->getId(),
                'includePostage' => true,
                'orderReturnLines' => [
                    ['orderProduct' => '/api/admin/order_products/'.$second->getId(), 'quantity' => 1.0],
                ],
            ],
            token: $token,
        );
        $competing = $this->finishCompetingRequest();

        self::assertSame(422, $response->getStatusCode(), 'The second return was granted the postage the first one took while it waited: '.$response->getContent().' / competing request: '.$competing);
        self::assertStringContainsString('postage of this order is already included', (string) $response->getContent());
        self::assertSame(1, $this->committedReturnsCarryingThePostage($order));
    }

    /**
     * One request names the lines of an order as [B, A], another one as
     * [A, B]. Locked in the order of each request, each would hold its first
     * line and wait for the other's: both are locked by increasing id instead,
     * and the request naming [B, A] waits on A without holding B.
     */
    public function testTwoRequestsNamingTheSameLinesInOppositeOrdersDoNotDeadlock(): void
    {
        [$order, $customer, [$first, $second]] = $this->paidOrderWithLines(1.0, 1.0);
        $token = $this->authenticateAsCustomer($customer);

        $this->startCompetingRequest(
            $this->mainThreadId(),
            [['ballast', self::BALLAST_ROWS], ['lock', 'order_product', (int) $first->getId()]],
            [['lock', 'order_product', (int) $second->getId()]],
            commit: false,
        );

        $response = $this->openFrontReturn($token, $order, [[$second, 1.0], [$first, 1.0]]);
        $competing = $this->finishCompetingRequest();

        self::assertSame(201, $response->getStatusCode(), 'The request naming its lines in decreasing order ran into the one naming them in increasing order: '.$response->getContent().' / competing request: '.$competing);
        self::assertStringContainsString('rolled back', $competing);
    }

    /**
     * A deadlock the database breaks anyway - a module locking the lines its
     * own way - is not a failure of the shop: the request it gives up on is
     * answered 409, nothing written, and may be sent again.
     */
    public function testADeadlockIsAConflictToRetryNotAServerError(): void
    {
        [$order, $customer, [$first, $second]] = $this->paidOrderWithLines(1.0, 1.0);
        $token = $this->authenticateAsCustomer($customer);

        $this->startCompetingRequest(
            $this->mainThreadId(),
            [['ballast', self::BALLAST_ROWS], ['lock', 'order_product', (int) $second->getId()]],
            [['lock', 'order_product', (int) $first->getId()]],
            commit: false,
        );

        $response = $this->openFrontReturn($token, $order, [[$first, 1.0], [$second, 1.0]]);
        $competing = $this->finishCompetingRequest();

        self::assertSame(409, $response->getStatusCode(), 'A deadlock must come back as a conflict: '.$response->getContent().' / competing request: '.$competing);
        self::assertSame(0.0, $this->committedReturnedQuantity($first), 'The request the database gave up on wrote nothing.');

        // The database ended the transaction itself: Propel must not keep
        // counting it, or every later transaction of the process is a no-op.
        $connection = $this->mainConnection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        self::assertFalse($connection->inTransaction());
        self::assertSame(0, $connection->getNestedTransactionCount(), 'Propel still counts the transaction the deadlock ended.');
    }

    /**
     * A request locks its order, then its lines, and each row once: the
     * eligibility check asking for the same rows again inside the transaction
     * does not send a second round of locks. The order of the lines is proven
     * by testTwoRequestsNamingTheSameLinesInOppositeOrdersDoNotDeadlock().
     */
    public function testAReturnLocksItsOrderThenItsLinesOnceEach(): void
    {
        [$order, $customer, [$first, $second]] = $this->paidOrderWithLines(1.0, 1.0);
        $token = $this->authenticateAsCustomer($customer);

        $response = null;
        $statements = $this->recordSqlQueries(function () use (&$response, $token, $order, $first, $second): void {
            $response = $this->openFrontReturn($token, $order, [[$second, 1.0], [$first, 1.0]]);
        });

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        $locked = [];
        foreach ($statements as $statement) {
            if (preg_match('/FROM `(order|order_product)` WHERE `id` = \S+ FOR UPDATE/', $statement, $match)) {
                $locked[] = $match[1];
            }
        }

        self::assertSame(
            ['order', 'order_product', 'order_product'],
            $locked,
            'The order first, then its two lines, each once: '.implode(' | ', $statements),
        );
    }

    /**
     * One admin lowers the quantity of a return line from 2 to 1 while the
     * request of another one, which the server read at 2, records 2 units
     * received. That second patch waits on the first one's locks, then finds
     * the line no longer as the server read it: it is refused as a conflict,
     * to be sent again on the line as it now is, and writes nothing. Checked
     * against the committed quantity instead, it would still decide on the 2
     * read before the locks. This is the window between that read and the
     * locks; two admins who read the line at different times are not covered.
     */
    public function testAPatchOnALineAnotherPatchChangedSinceItWasReadIsAConflict(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);
        $token = $this->authenticateAsAdmin($this->committedAdmin());

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity' => 1.0]],
        ]);

        $response = $this->jsonRequest('PATCH', '/api/admin/order_return_lines/'.$lineId, ['quantityReceived' => 2.0], $token, 'merge-patch+json');
        $competing = $this->finishCompetingRequest();

        self::assertSame(['quantity' => 1.0, 'received' => 0.0], $this->committedLineQuantities($lineId), 'The patch wrote over a line another one changed while it waited: '.$response->getContent().' / competing request: '.$competing);
        self::assertSame(409, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('changed since it was read', (string) $response->getContent());
    }

    /**
     * The competing patch changes a field the second one does not send. The
     * second one is refused all the same: kept, the fields it sends would be
     * written next to a state of the line the server did not read.
     */
    public function testAPatchIsAConflictEvenWhenTheLineChangedOnAFieldItDoesNotSend(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);
        $token = $this->authenticateAsAdmin($this->committedAdmin());

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['received_condition' => 'damaged']],
        ]);

        $response = $this->jsonRequest('PATCH', '/api/admin/order_return_lines/'.$lineId, ['quantityReceived' => 1.0], $token, 'merge-patch+json');
        $competing = $this->finishCompetingRequest();

        self::assertSame(409, $response->getStatusCode(), $response->getContent().' / competing request: '.$competing);
        self::assertSame(['quantity' => 2.0, 'received' => 0.0], $this->committedLineQuantities($lineId));
        self::assertSame('damaged', $this->committedLineCondition($lineId));
    }

    /**
     * The race above with Propel's instance pool on, as in production: API
     * Platform read the line before the locks and left it in the pool, and a
     * pooled line comes back from a later query as it was, whatever the
     * database now holds.
     */
    public function testAPatchSeesTheLineAnotherPatchCommittedThroughTheInstancePool(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);
        $token = $this->authenticateAsAdmin($this->committedAdmin());

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity' => 1.0]],
        ]);

        $response = $this->withInstancePooling(fn (): Response => $this->jsonRequest('PATCH', '/api/admin/order_return_lines/'.$lineId, ['quantityReceived' => 2.0], $token, 'merge-patch+json'));
        $competing = $this->finishCompetingRequest();

        self::assertSame(['quantity' => 1.0, 'received' => 0.0], $this->committedLineQuantities($lineId), 'The patch decided on the line the pool kept from before the locks: '.$response->getContent().' / competing request: '.$competing);
        self::assertSame(409, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * The same race through the reception: the quantities it is handed are
     * checked against the line as the competing patch left it.
     */
    public function testTheReceptionChecksItsQuantitiesAgainstTheLineACompetingPatchCommitted(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity' => 1.0]],
        ]);

        $refusal = null;

        try {
            $this->receive($lineId, 2.0);
        } catch (ReturnNotAllowedException $exception) {
            $refusal = $exception->getMessage();
        }

        $competing = $this->finishCompetingRequest();

        self::assertSame(['quantity' => 1.0, 'received' => 0.0], $this->committedLineQuantities($lineId), 'The reception recorded more units received than the line asks for. Competing request: '.$competing);
        self::assertNotNull($refusal);
        self::assertStringContainsString('cannot exceed the returned quantity', $refusal);
    }

    /**
     * The reception race with the instance pool on, the lines of the return
     * loaded by its caller before the locks: the reception reads them again.
     */
    public function testTheReceptionSeesTheLineACompetingPatchCommittedThroughTheInstancePool(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity' => 1.0]],
        ]);

        $refusal = null;

        try {
            $this->withInstancePooling(function () use ($lineId): void {
                $this->receive($lineId, 2.0, loadTheLinesFirst: true);
            });
        } catch (ReturnNotAllowedException $exception) {
            $refusal = $exception->getMessage();
        }

        $competing = $this->finishCompetingRequest();

        self::assertSame(['quantity' => 1.0, 'received' => 0.0], $this->committedLineQuantities($lineId), 'The reception decided on the lines the pool kept from before the locks. Competing request: '.$competing);
        self::assertNotNull($refusal);
        self::assertStringContainsString('cannot exceed the returned quantity', $refusal);
    }

    /**
     * A module may receive a return inside a transaction of its own, after
     * having read in it: the picture of that transaction predates the patch a
     * competing request committed while the reception waited on its locks. The
     * return and its lines are read again with locking reads, which see the
     * last commit, and the quantities are checked against the line as it now is.
     */
    public function testAReceptionInACallerTransactionThatAlreadyReadSeesTheLineACompetingPatchCommitted(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity' => 1.0]],
        ]);

        $refusal = null;
        $this->mainConnection()->beginTransaction();

        try {
            $this->readTheLineInTheCallerTransaction($lineId, 2.0);
            $this->receive($lineId, 2.0);
        } catch (ReturnNotAllowedException $exception) {
            $refusal = $exception->getMessage();
        } finally {
            $this->mainConnection()->rollBack();
        }

        $competing = $this->finishCompetingRequest();

        self::assertNotNull($refusal, 'The reception recorded two units received on a line the competing patch lowered to one: it decided on the picture its caller transaction took before. Competing request: '.$competing);
        self::assertStringContainsString('cannot exceed the returned quantity', $refusal);
    }

    /**
     * The same for the status change: moving the return to received in a
     * caller transaction that already read refunds the quantity received as
     * the competing patch committed it, not as the caller transaction saw it.
     */
    public function testAMoveToReceivedInACallerTransactionThatAlreadyReadRefundsWhatACompetingPatchCommitted(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);
        $returnId = $this->returnIdOf($lineId);

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity_received' => 2.0]],
        ]);

        $refundInTheTransaction = null;
        $this->mainConnection()->beginTransaction();

        try {
            $this->readTheLineInTheCallerTransaction($lineId, 2.0);

            $return = OrderReturnQuery::create()->findPk($returnId);
            self::assertNotNull($return);
            $this->getService(EventDispatcherInterface::class)->dispatch(
                (new OrderReturnEvent($return))->setTargetStatusId(OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_RECEIVED)),
                TheliaEvents::ORDER_RETURN_UPDATE_STATUS,
            );

            $statement = $this->mainConnection()->prepare('SELECT refund_amount FROM order_return WHERE id = :id');
            $statement->execute([':id' => $returnId]);
            $refundInTheTransaction = (float) $statement->fetchColumn();
        } finally {
            $this->mainConnection()->rollBack();
        }

        $competing = $this->finishCompetingRequest();

        self::assertSame(20.0, $refundInTheTransaction, 'Two units of 10.00 received while the move waited: the refund was computed on the quantity the caller transaction saw before. Competing request: '.$competing);
    }

    /**
     * Moving a return to received restocks and refunds the received
     * quantities: those of the line as a patch committed while the move
     * waited on its locks, not as they were before.
     */
    public function testAMoveToReceivedRefundsTheQuantityACompetingPatchCommitted(): void
    {
        [$order, $customer, [$orderProduct]] = $this->paidOrderWithLines(2.0);
        $lineId = $this->committedReturnLine($order, $customer, $orderProduct, 2.0);
        $returnId = $this->returnIdOf($lineId);
        $token = $this->authenticateAsAdmin($this->committedAdmin());

        $this->startCompetingRequest($this->mainThreadId(), [
            ['lock', 'order', (int) $order->getId()],
            ['lock', 'order_product', (int) $orderProduct->getId()],
            ['update', 'order_return_line', $lineId, ['quantity_received' => 2.0]],
        ]);

        $response = $this->jsonRequest('POST', '/api/admin/order_returns/'.$returnId.'/transition', ['statusCode' => OrderReturnStatus::CODE_RECEIVED], token: $token);
        $competing = $this->finishCompetingRequest();

        self::assertSame(200, $response->getStatusCode(), $response->getContent().' / competing request: '.$competing);
        self::assertSame(20.0, $this->committedReturnRefund($returnId), 'Two units of 10.00 received while the move waited: the refund was computed on the quantity read before the locks.');
    }

    /**
     * A deadlock the database breaks on a status change is answered as one on
     * a return opening is: 409, nothing written.
     */
    public function testADeadlockOnAStatusChangeIsAConflictToRetry(): void
    {
        [$order, $customer, [$first, $second]] = $this->paidOrderWithLines(1.0, 1.0);
        $lineId = $this->committedReturnLine($order, $customer, $second, 1.0, $first);
        $returnId = $this->returnIdOf($lineId);
        $token = $this->authenticateAsAdmin($this->committedAdmin());

        $this->startCompetingRequest(
            $this->mainThreadId(),
            [['ballast', self::BALLAST_ROWS], ['lock', 'order_product', (int) $second->getId()]],
            [['lock', 'order_product', (int) $first->getId()]],
            commit: false,
        );

        $response = $this->jsonRequest('POST', '/api/admin/order_returns/'.$returnId.'/transition', ['statusCode' => OrderReturnStatus::CODE_RECEIVED], token: $token);
        $competing = $this->finishCompetingRequest();

        self::assertSame(409, $response->getStatusCode(), 'A deadlock on a status change must come back as a conflict: '.$response->getContent().' / competing request: '.$competing);
        self::assertSame(OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_ACCEPTED), $this->committedReturnStatusId($returnId), 'The status change the database gave up on wrote nothing.');
    }

    /**
     * The reception locks what a return is opened and patched under, in the
     * same order - the order, then its lines - before the return itself. The
     * order of the lines between them is proven by
     * testTwoRequestsNamingTheSameLinesInOppositeOrdersDoNotDeadlock().
     */
    public function testTheReceptionLocksTheOrderThenItsLinesThenTheReturn(): void
    {
        [$order, $customer, [$first, $second]] = $this->paidOrderWithLines(1.0, 1.0);
        $lineId = $this->committedReturnLine($order, $customer, $second, 1.0, $first);

        $statements = $this->recordSqlQueries(function () use ($lineId): void {
            $this->receive($lineId, 1.0);
        });

        $locked = [];
        foreach ($statements as $statement) {
            if (preg_match('/FROM `(order|order_product|order_return)` WHERE `id` = \S+ FOR UPDATE/', $statement, $match)) {
                $locked[] = $match[1];
            }
        }

        self::assertSame(
            ['order', 'order_product', 'order_product', 'order_return'],
            $locked,
            'The order first, then its two lines, then the return, each once: '.implode(' | ', $statements),
        );
    }

    /**
     * An accepted return committed for real, with one line on $orderProduct and,
     * when given, a second one on $otherOrderProduct.
     *
     * @return int the id of the line on $orderProduct
     */
    private function committedReturnLine(Order $order, Customer $customer, OrderProduct $orderProduct, float $quantity, ?OrderProduct $otherOrderProduct = null): int
    {
        $acceptedStatusId = OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_ACCEPTED);
        self::assertNotNull($acceptedStatusId);

        $connection = $this->secondConnection();
        $connection->prepare('INSERT INTO order_return (order_id, customer_id, status_id, include_postage, created_at, updated_at) VALUES (:order, :customer, :status, 0, NOW(), NOW())')
            ->execute([':order' => $order->getId(), ':customer' => $customer->getId(), ':status' => $acceptedStatusId]);
        $returnId = (int) $connection->lastInsertId();

        $insertLine = $connection->prepare('INSERT INTO order_return_line (order_return_id, order_product_id, quantity, quantity_received, created_at, updated_at) VALUES (:return, :line, :quantity, 0, NOW(), NOW())');

        if (null !== $otherOrderProduct) {
            $insertLine->execute([':return' => $returnId, ':line' => $otherOrderProduct->getId(), ':quantity' => $quantity]);
        }

        $insertLine->execute([':return' => $returnId, ':line' => $orderProduct->getId(), ':quantity' => $quantity]);

        return (int) $connection->lastInsertId();
    }

    private function receive(int $lineId, float $quantity, bool $loadTheLinesFirst = false): void
    {
        $return = OrderReturnQuery::create()->findPk($this->returnIdOf($lineId));
        self::assertNotNull($return);

        if ($loadTheLinesFirst) {
            self::assertCount(1, $return->getOrderReturnLines());
        }

        $this->getService(EventDispatcherInterface::class)->dispatch(
            (new OrderReturnEvent($return))->setReceivedLines([$lineId => ['quantity' => $quantity]]),
            TheliaEvents::ORDER_RETURN_RECEIVE,
        );
    }

    /**
     * The first plain read of a caller transaction, which takes its picture.
     */
    private function readTheLineInTheCallerTransaction(int $lineId, float $expectedQuantity): void
    {
        OrderReturnLineTableMap::clearInstancePool();
        $line = OrderReturnLineQuery::create()->findPk($lineId, $this->mainConnection());
        self::assertNotNull($line);
        self::assertSame($expectedQuantity, (float) $line->getQuantity());
        OrderReturnLineTableMap::clearInstancePool();
    }

    private function returnIdOf(int $lineId): int
    {
        $statement = $this->secondConnection()->prepare('SELECT order_return_id FROM order_return_line WHERE id = :id');
        $statement->execute([':id' => $lineId]);

        return (int) $statement->fetchColumn();
    }

    private function committedLineCondition(int $lineId): ?string
    {
        $statement = $this->secondConnection()->prepare('SELECT received_condition FROM order_return_line WHERE id = :id');
        $statement->execute([':id' => $lineId]);
        $condition = $statement->fetchColumn();

        return false === $condition || null === $condition ? null : (string) $condition;
    }

    private function committedReturnRefund(int $returnId): float
    {
        $statement = $this->secondConnection()->prepare('SELECT refund_amount FROM order_return WHERE id = :id');
        $statement->execute([':id' => $returnId]);

        return (float) $statement->fetchColumn();
    }

    private function committedReturnStatusId(int $returnId): int
    {
        $statement = $this->secondConnection()->prepare('SELECT status_id FROM order_return WHERE id = :id');
        $statement->execute([':id' => $returnId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Runs $work with Propel's instance pool on, as in production, from an
     * empty pool; IntegrationTestCase turns it off for every other test.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    private function withInstancePooling(callable $work): mixed
    {
        Propel::enableInstancePooling();
        self::forgetPooledModels();

        try {
            return $work();
        } finally {
            self::forgetPooledModels();
            Propel::disableInstancePooling();
        }
    }

    /**
     * @return array{quantity: float, received: float}
     */
    private function committedLineQuantities(int $lineId): array
    {
        $statement = $this->secondConnection()->prepare('SELECT quantity, quantity_received FROM order_return_line WHERE id = :id');
        $statement->execute([':id' => $lineId]);
        $row = $statement->fetch(\PDO::FETCH_NUM);
        self::assertIsArray($row);

        return ['quantity' => (float) $row[0], 'received' => (float) $row[1]];
    }

    /**
     * @param list<array{OrderProduct, float}> $lines
     */
    private function openFrontReturn(string $token, Order $order, array $lines): Response
    {
        return $this->jsonRequest(
            'POST',
            '/api/front/account/order_returns',
            [
                'order' => '/api/front/account/orders/'.$order->getId(),
                'orderReturnLines' => array_map(
                    static fn (array $line): array => ['orderProduct' => '/api/front/account/order_products/'.$line[0]->getId(), 'quantity' => $line[1]],
                    $lines,
                ),
            ],
            token: $token,
        );
    }

    private function openReturnThroughTheEvent(Order $order, Customer $customer, OrderProduct $orderProduct, float $quantity): void
    {
        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setCreatedByAdmin(false)
            ->setIncludePostage(false);
        $return->addOrderReturnLine((new OrderReturnLine())->setOrderProduct($orderProduct)->setQuantity($quantity));

        $this->getService(EventDispatcherInterface::class)->dispatch(new OrderReturnEvent($return), TheliaEvents::ORDER_RETURN_CREATE);
    }

    private function committedReturnedQuantity(OrderProduct $orderProduct): float
    {
        $statement = $this->secondConnection()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM order_return_line WHERE order_product_id = :id');
        $statement->execute([':id' => $orderProduct->getId()]);

        return (float) $statement->fetchColumn();
    }

    private function committedReturnsCarryingThePostage(Order $order): int
    {
        $statement = $this->secondConnection()->prepare('SELECT COUNT(*) FROM order_return WHERE order_id = :id AND include_postage = 1');
        $statement->execute([':id' => $order->getId()]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{Order, Customer, list<OrderProduct>}
     */
    private function paidOrderWithLines(float ...$quantities): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID, 'postage' => '5.000000']);
        $this->orderIds[] = (int) $order->getId();

        $lines = [];
        foreach ($quantities as $quantity) {
            $lines[] = $this->factory->orderProduct($order, ['quantity' => $quantity, 'productRef' => uniqid('REF-'), 'productSaleElementsRef' => uniqid('PSE-')]);
        }

        return [$order, $customer, $lines];
    }

    private function committedAdmin(): Admin
    {
        $admin = $this->factory->admin(['password' => 'password']);
        $this->adminIds[] = (int) $admin->getId();

        return $admin;
    }

    private function requestedStatusId(): int
    {
        $statusId = OrderReturnStatusQuery::create()->findIdByCode(OrderReturnStatus::CODE_REQUESTED);
        self::assertNotNull($statusId, "Seeded order return status 'requested' is missing - run bin/test-prepare.");

        return $statusId;
    }

    /**
     * The database session of the request under test, the one the competing
     * request watches.
     */
    private function mainThreadId(): int
    {
        return (int) $this->mainConnection()->query('SELECT CONNECTION_ID()')->fetchColumn();
    }

    private function mainConnection(): ConnectionInterface
    {
        return Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
    }
}
