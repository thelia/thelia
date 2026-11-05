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

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Domain\Catalog\Stock\StockRowLocker;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductQuery;
use Thelia\Test\ApiTestCase;

/**
 * Two buyers add the last unit to their carts through the API at the same moment, in a shop that
 * reserves from the cart on: two PHP processes with their own kernel and connection, released
 * together once this one lets go of the stock row. The write of a line shares that row (its
 * foreign key) until it commits; a hold made inside that write would turn the two buyers into a
 * deadlock, whose loser is answered with a line the database rolled back. Each answer is a line
 * that is in the cart or a refusal, never an error, and the unit is held once.
 *
 * The fixtures are committed, and deleted by hand.
 */
#[Group('concurrency')]
final class CartItemConcurrentHoldApiTest extends ApiTestCase
{
    private const PROCESS_TIMEOUT = 60;

    protected bool $useTransaction = false;

    private ?ConnectionInterface $otherSession = null;

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    /** @var list<int> */
    private array $customerIds = [];

    /** @var list<int> */
    private array $productIds = [];

    /** @var list<int> */
    private array $categoryIds = [];

    /** @var array<string, string|null> */
    private array $settingsBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['check-available-stock', 'allow_negative_stock', StockSettings::RESERVATION_ENABLED, StockSettings::RESERVATION_SCOPE, StockSettings::RESERVATION_DURATION] as $name) {
            $this->settingsBefore[$name] = ConfigQuery::create()->findOneByName($name)?->getValue();
        }

        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'cart');
        ConfigQuery::write(StockSettings::RESERVATION_DURATION, '15');
        ConfigQuery::resetCache();
    }

    protected function tearDown(): void
    {
        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        while ($connection->inTransaction()) {
            $connection->rollBack();
        }

        try {
            // The carts and their lines follow the customer, the sale elements the product.
            CustomerQuery::create()->filterById($this->customerIds)->delete($connection);
            ProductQuery::create()->filterById($this->productIds)->delete($connection);
            CategoryQuery::create()->filterById($this->categoryIds)->delete($connection);
        } finally {
            foreach ($this->settingsBefore as $name => $value) {
                ConfigQuery::write($name, $value ?? '');
            }

            ConfigQuery::resetCache();
            $this->otherSession = null;
        }

        parent::tearDown();
    }

    public function testTwoAdditionsOfTheLastUnitAtOnceHoldItOnceAndNeverFail(): void
    {
        $factory = $this->createFixtureFactory();
        $category = $factory->category();
        $this->categoryIds[] = (int) $category->getId();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency(), ['baseQuantity' => 1]);
        $this->productIds[] = (int) $product->getId();
        $productSaleElementsId = (int) $product->getProductSaleElementss()->getFirst()->getId();

        $buyers = [];

        foreach (['first', 'second'] as $buyer) {
            $customer = $factory->customer($factory->customerTitle(), ['password' => 'password']);
            $this->customerIds[] = (int) $customer->getId();
            $buyers[$buyer] = [$this->authenticateAsCustomer($customer), (int) $factory->cart($customer)->getId()];
        }

        $connection = Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $processes = [];
        $connection->beginTransaction();

        try {
            // Both writes wait on the row this one locks, and go on together once it commits.
            $this->getService(StockRowLocker::class)->lock([$productSaleElementsId], $connection);

            foreach ($buyers as $buyer => [$token, $cartId]) {
                $processes[$buyer] = $this->inAnotherProcess($token, $cartId, $productSaleElementsId);
                $this->waitUntilTheOtherProcessesWaitOnTheStock(\count($processes), $processes);
            }
        } finally {
            $connection->commit();
        }

        $outcomes = array_map(fn ($process): array => $this->waitFor($process), $processes);

        foreach ($outcomes as $buyer => $outcome) {
            self::assertContains($outcome['status'], [201, 422], 'The '.$buyer.' buyer got an error: '.$outcome['body']);

            if (201 === $outcome['status']) {
                $lineId = (int) (json_decode($outcome['body'], true, 512, \JSON_THROW_ON_ERROR)['id'] ?? 0);
                self::assertSame(1, (int) $this->otherSession()->query('SELECT COUNT(*) FROM cart_item WHERE id = '.$lineId)->fetchColumn(), 'The line the '.$buyer.' buyer was answered with is in the cart.');
            }
        }

        self::assertSame(1, (int) $this->otherSession()->query(
            'SELECT COUNT(*) FROM cart_item WHERE product_sale_elements_id = '.$productSaleElementsId.' AND reserved_until > NOW()',
        )->fetchColumn(), 'The last unit is held once.');
        self::assertSame(1.0, (float) $this->otherSession()->query(
            'SELECT quantity FROM product_sale_elements WHERE id = '.$productSaleElementsId,
        )->fetchColumn());
    }

    /**
     * @return resource
     */
    private function inAnotherProcess(string $token, int $cartId, int $productSaleElementsId)
    {
        $process = proc_open(
            [\PHP_BINARY, \dirname(__DIR__, 2).'/Support/Order/add-to-cart-through-the-api-in-another-process.php', $token, (string) $cartId, (string) $productSaleElementsId, '1'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * As many other processes as asked are blocked on a write of a cart line, which waits on the
     * stock row this one locked: read off the processes of the server, seen twice in a row.
     *
     * @param array<string, resource> $processes
     */
    private function waitUntilTheOtherProcessesWaitOnTheStock(int $expected, array $processes): void
    {
        $ownThreads = [
            (int) Propel::getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME)->query('SELECT CONNECTION_ID()')->fetchColumn(),
            (int) $this->otherSession()->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ];
        $waiting = $this->otherSession()->prepare(
            "SELECT ID FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND COMMAND IN ('Query', 'Execute') AND INFO LIKE '%cart_item%' AND ID NOT IN (".implode(',', $ownThreads).')',
        );
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;
        $seen = [];

        do {
            foreach ($processes as $process) {
                if (!proc_get_status($process)['running']) {
                    self::fail('Another process ended before waiting: '.stream_get_contents($this->pipes[(int) $process][1]).stream_get_contents($this->pipes[(int) $process][2]));
                }
            }

            $waiting->execute();
            $threads = $waiting->fetchAll(\PDO::FETCH_COLUMN);
            $waiting->closeCursor();

            if (\count(array_intersect($threads, $seen)) >= $expected) {
                return;
            }

            $seen = $threads;
            usleep(200000);
        } while (microtime(true) < $deadline);

        self::fail('The other processes never waited on the stock.');
    }

    /**
     * @param resource $process
     *
     * @return array{status: int, body: string}
     */
    private function waitFor($process): array
    {
        [1 => $stdout, 2 => $stderr] = $this->pipes[(int) $process];
        unset($this->pipes[(int) $process]);
        $output = (string) stream_get_contents($stdout);
        $errors = (string) stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);

        self::assertSame(0, proc_close($process), 'The other process failed: '.$errors.$output);

        // The JSON is the last thing the process prints, after whatever booting it wrote.
        return json_decode(substr($output, (int) strrpos($output, '{"status"')), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds: it reads what
     * is committed, and only that.
     */
    private function otherSession(): ConnectionInterface
    {
        if (null !== $this->otherSession) {
            return $this->otherSession;
        }

        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(ProductSaleElementsTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return $this->otherSession = ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(ProductSaleElementsTableMap::DATABASE_NAME),
        );
    }
}
