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

namespace Thelia\Tests\Integration\Domain\Customer;

use Thelia\Domain\Customer\Service\CustomerAnonymizer;
use Thelia\Domain\Customer\Service\CustomerPersonalDataExporter;
use Thelia\Model\Customer;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderReturnVersionQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A customer's returns are personal data: they belong in the portability export,
 * and their free text disappears when the account is anonymized while the return
 * itself stays for the accounting record.
 */
final class CustomerReturnsPersonalDataTest extends IntegrationTestCase
{
    private const COMMENT = 'The parcel arrived open.';

    private const REFUSAL_REASON = 'Refused: Mrs Martin sent back a different item.';

    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testTheExportListsTheCustomerReturns(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->returnWithComment($customer);

        $exporter = $this->getService(CustomerPersonalDataExporter::class);
        $data = $exporter->export($customer);

        self::assertArrayHasKey('order_returns', $data);
        self::assertCount(1, $data['order_returns']);
        self::assertSame(self::COMMENT, $data['order_returns'][0]['customer_comment']);
    }

    public function testAnonymizationClearsTheReturnCommentButKeepsTheReturn(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $return = $this->returnWithComment($customer);

        // Without this, a behavior that stopped writing versions would make the
        // assertion below pass while the purge did nothing at all.
        self::assertGreaterThan(
            0,
            OrderReturnVersionQuery::create()->filterById($return->getId())->count($this->getPropelConnection()),
            'The versionable history is expected to hold the comment before the account is anonymized.',
        );

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);

        $reloaded = OrderReturnQuery::create()->findPk($return->getId(), $this->getPropelConnection());
        self::assertNotNull($reloaded, 'the return itself is kept for the accounting record');
        self::assertNull($reloaded->getCustomerComment(), 'the free text personal data is erased');

        self::assertSame(
            0,
            OrderReturnVersionQuery::create()->filterById($return->getId())->count($this->getPropelConnection()),
            'the versionable history that still held the comment is dropped',
        );
    }

    /**
     * The merchant types the refusal reason by hand, and names the customer in
     * it as often as not ("returned by Mrs Martin, parcel opened"). It is the
     * same kind of free text as the customer comment, and it goes the same way.
     */
    public function testAnonymizationClearsTheMerchantRefusalReason(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $return = $this->returnWithComment($customer, self::REFUSAL_REASON);

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);

        $reloaded = OrderReturnQuery::create()->findPk($return->getId(), $this->getPropelConnection());
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getRefusalReason(), 'the merchant free text can name the customer too');
    }

    /**
     * Dropping every version row leaves the return pointing at a revision that
     * no longer exists, which is what the versionable behavior reads to build
     * the next one.
     */
    public function testAnonymizationLeavesNoDanglingVersionNumber(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $return = $this->returnWithComment($customer);

        $this->getService(CustomerAnonymizer::class)->anonymize($customer);

        $reloaded = OrderReturnQuery::create()->findPk($return->getId(), $this->getPropelConnection());
        self::assertNotNull($reloaded);
        self::assertSame(0, $reloaded->getVersion(), 'no version row is left for the number to point at');
    }

    private function returnWithComment(Customer $customer, ?string $refusalReason = null): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $status = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status)
            ->setCustomerComment(self::COMMENT)
            ->setRefusalReason($refusalReason);
        $return->save($this->getPropelConnection());

        return $return;
    }
}
