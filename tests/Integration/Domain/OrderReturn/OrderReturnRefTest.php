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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Connection\PdoConnection;
use Thelia\Domain\OrderReturn\Service\OrderReturnRefGeneratorInterface;
use Thelia\Domain\OrderReturn\Service\SequenceOrderReturnRefGenerator;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\Customer;
use Thelia\Model\OrderReturn;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The reference a return is known by: its shape, its uniqueness when two
 * requests arrive together, and the fact that a shop can take the numbering
 * over.
 */
final class OrderReturnRefTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        // The model keeps the generator in a static, so a test that replaces it
        // has to hand the shop's own back.
        OrderReturn::setRefGenerator($this->getService(OrderReturnRefGeneratorInterface::class));
        OrderReturnStatusQuery::resetCache();

        parent::tearDown();
    }

    public function testAReferenceIsRetFollowedByTwelveDigits(): void
    {
        self::assertSame('RET000000000001', SequenceOrderReturnRefGenerator::format(1));
        self::assertSame('RET000000001051', SequenceOrderReturnRefGenerator::format(1051));
        self::assertSame('RET999999999999', SequenceOrderReturnRefGenerator::format(999999999999));

        $return = $this->openReturn($this->factory->customer($this->factory->customerTitle()));

        self::assertMatchesRegularExpression('/^RET\d{12}$/', (string) $return->getRef());
    }

    public function testTwoReturnsNeverShareAReference(): void
    {
        $customer = $this->factory->customer($this->factory->customerTitle());

        $first = $this->openReturn($customer);
        $second = $this->openReturn($customer);

        self::assertNotSame($first->getRef(), $second->getRef());
        self::assertSame(
            $this->numberOf($first) + 1,
            $this->numberOf($second),
            'The series has a hole in it.',
        );
    }

    /**
     * Two requests arriving together must not read the same counter value. The
     * allocation holds the counter row until the transaction that writes the
     * return ends, so a second connection cannot get past it - here it is made
     * to give up after a second rather than wait.
     *
     * The first transaction is rolled back, so the counter is left exactly as
     * it was found and the rest of the suite numbers from where it was.
     */
    public function testASecondAllocationCannotReadTheNumberTheFirstIsHolding(): void
    {
        $generator = new GaplessSequenceGenerator();
        $holder = $this->dedicatedConnection();
        $contender = $this->dedicatedConnection();
        $contender->prepare('SET SESSION innodb_lock_wait_timeout = 1')->execute();

        $holder->beginTransaction();
        $held = $generator->next(SequenceOrderReturnRefGenerator::SEQUENCE_NAME, $holder);

        try {
            $stolen = null;

            try {
                $contender->beginTransaction();
                $stolen = $generator->next(SequenceOrderReturnRefGenerator::SEQUENCE_NAME, $contender);
                $contender->commit();
            } catch (\Throwable $exception) {
                $contender->rollBack();
                self::assertStringContainsString('lock', strtolower($exception->getMessage()));
            }

            self::assertNull($stolen, \sprintf('A second request was handed number %s while the first held it.', var_export($stolen, true)));
        } finally {
            $holder->rollBack();
        }

        self::assertGreaterThan(0, $held);
    }

    /**
     * A shop bound to its own numbering series aliases the interface to its own
     * service, and every return saved from anywhere takes its references.
     */
    public function testAShopCanTakeTheNumberingOver(): void
    {
        OrderReturn::setRefGenerator(new class implements OrderReturnRefGeneratorInterface {
            public function generate(ConnectionInterface $connection): string
            {
                return 'SAV-2026-0001';
            }
        });

        $return = $this->openReturn($this->factory->customer($this->factory->customerTitle()));

        self::assertSame('SAV-2026-0001', $return->getRef());
    }

    public function testTheShopGeneratorIsTheSequenceOneByDefault(): void
    {
        self::assertInstanceOf(
            SequenceOrderReturnRefGenerator::class,
            $this->getService(OrderReturnRefGeneratorInterface::class),
        );
    }

    private function openReturn(Customer $customer): OrderReturn
    {
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $status = OrderReturnStatusQuery::create()->findOneByCode(OrderReturnStatus::CODE_REQUESTED);

        $return = (new OrderReturn())
            ->setOrder($order)
            ->setCustomer($customer)
            ->setOrderReturnStatus($status);
        $return->save($this->getPropelConnection());

        return $return;
    }

    private function numberOf(OrderReturn $return): int
    {
        return (int) substr((string) $return->getRef(), 3);
    }

    private function dedicatedConnection(): ConnectionWrapper
    {
        return new ConnectionWrapper(new PdoConnection(
            \sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                $_SERVER['DATABASE_HOST'],
                $_SERVER['DATABASE_PORT'] ?? '3306',
                $_SERVER['DATABASE_NAME'],
            ),
            $_SERVER['DATABASE_USER'],
            $_SERVER['DATABASE_PASSWORD'],
        ));
    }
}
