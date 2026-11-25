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

namespace Thelia\Tests\Integration\Quote\Acceptance;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Component\Lock\LockFactory;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Quote\Acceptance\QuoteAcceptance;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\QuoteFacade;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartItemTableMap;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Model\TaxRuleCountry;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A quote accepted twice at once, and the cart of a quote ordered twice at once: one passage
 * from quoted to accepted and one event, one order.
 *
 * The competing requests are PHP processes of their own, each with its kernel and its connection,
 * started while this process holds the row of the quote in a transaction it has not ended: they
 * all arrive at the quote together, and are released together. What the test writes is committed
 * (the other processes could not see it otherwise), so it is taken back by hand: every row the
 * test added is deleted, and the settings, the consents and the sequences are put back as they
 * were.
 */
#[Group('concurrency')]
final class QuoteAcceptanceConcurrencyTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    private const int PROCESS_TIMEOUT = 90;

    private ?ConnectionInterface $otherSession = null;

    private ?ConnectionInterface $connection = null;

    private FixtureFactory $fixtures;

    /** @var array<string, int> the greatest id of every table that has one, before the test */
    private array $watermarks = [];

    /** @var array<string, string|null> */
    private array $configBefore = [];

    /** @var array<int, int> */
    private array $consentsBefore = [];

    /** @var array<string, int> */
    private array $sequencesBefore = [];

    /** @var array<int, array<int, resource>> */
    private array $pipes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME);
        $this->otherSession = $this->openOtherSession();
        $this->rememberTheDatabase();
        $this->fixtures = $this->createFixtureFactory();
    }

    protected function tearDown(): void
    {
        try {
            while ($this->connection?->inTransaction()) {
                $this->connection->rollBack();
            }

            foreach ($this->pipes as $pipes) {
                foreach ($pipes as $pipe) {
                    if (\is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
            }

            $this->takeBackWhatTheTestWrote();
        } finally {
            Propel::disableInstancePooling();

            parent::tearDown();
        }
    }

    public function testTwoAcceptancesAtOnceMoveTheQuoteOnceAndTellTheShopOnce(): void
    {
        [$quote, $buyer] = $this->aQuoteTheBuyerCanAccept();

        $outcomes = $this->runWhileTheQuoteIsHeld($quote, [
            ['accept', (string) $quote->getId(), (string) $buyer->getId(), '2'],
            ['accept', (string) $quote->getId(), (string) $buyer->getId(), '2'],
        ], 1);

        self::assertCount(2, $outcomes);

        foreach ($outcomes as $outcome) {
            self::assertArrayNotHasKey('failed', $outcome, (string) json_encode($outcome));
            self::assertArrayNotHasKey('refused', $outcome, (string) json_encode($outcome));
        }

        self::assertSame(1, array_sum(array_column($outcomes, 'accepted_events')), 'one passage from quoted to accepted, one event');
        self::assertSame($outcomes[0]['cart'], $outcomes[1]['cart'], 'the second one is given the cart of the first');
        self::assertSame(1, CartQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame(QuoteState::Accepted->value, $this->stateOf($quote));
    }

    public function testAnAcceptanceWaitsForTheLockOfTheQuoteBeforeItReadsAnything(): void
    {
        [$quote, $buyer] = $this->aQuoteTheBuyerCanAccept();
        $lock = $this->getService(LockFactory::class)->createLock(QuoteAcceptance::LOCK_PREFIX.$quote->getId(), 60.0);
        self::assertTrue($lock->acquire());

        try {
            $process = $this->start(['accept', (string) $quote->getId(), (string) $buyer->getId(), '2']);
            sleep(4);

            self::assertTrue(proc_get_status($process)['running'], 'the other acceptance is under way: this one waits');
            self::assertSame(QuoteState::Quoted->value, $this->stateOf($quote));
        } finally {
            $lock->release();
        }

        $outcome = $this->waitFor($process);

        self::assertArrayNotHasKey('failed', $outcome, (string) json_encode($outcome));
        self::assertSame(1, $outcome['accepted_events']);
        self::assertSame(QuoteState::Accepted->value, $this->stateOf($quote));
    }

    public function testTwoOrdersOfTheSameQuoteAtOnceGiveOneOrder(): void
    {
        [$quote, $buyer] = $this->aQuoteTheBuyerCanAccept();
        $first = $this->getService(QuoteFacade::class)->accept($quote, $buyer, 2);
        $second = $this->anotherCartOf($first);

        $outcomes = $this->runWhileTheQuoteIsHeld($quote, [
            ['place', (string) $first->getId(), (string) $buyer->getId()],
            ['place', (string) $second->getId(), (string) $buyer->getId()],
        ], 2);

        foreach ($outcomes as $outcome) {
            self::assertArrayNotHasKey('failed', $outcome, (string) json_encode($outcome));
        }

        self::assertCount(1, array_filter($outcomes, static fn (array $outcome): bool => isset($outcome['order'])), 'one of them ordered');
        self::assertSame(['quote_already_ordered'], array_values(array_column(array_filter($outcomes, static fn (array $outcome): bool => isset($outcome['refused'])), 'refused')), 'the other was told so');
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame(1, OrderQuery::create()->filterByCartId([$first->getId(), $second->getId()])->count(), 'the carts of the quote hold one order between them');
        self::assertSame(QuoteState::Ordered->value, $this->stateOf($quote));
    }

    /**
     * A buyer in the default country, and a quote sent at its second revision:
     * 12 of A at 90, 15 of postage, 30 off, delivered by a carrier of the shop.
     *
     * @return array{Quote, Customer}
     */
    private function aQuoteTheBuyerCanAccept(): array
    {
        $vatRule = $this->fixtures->taxRule(['isDefault' => false]);
        $tax = $this->fixtures->tax(['requirements' => ['percent' => '20.000000'], 'title' => 'VAT 20']);
        (new TaxRuleCountry())->setTaxRuleId($vatRule->getId())->setCountryId(Country::getDefaultCountry()->getId())->setTaxId($tax->getId())->setPosition(1)->save($this->connection);

        ConfigQuery::write('quote_mode', 'alongside');
        ConfigQuery::write('quote_validity_days', '30');
        ConfigQuery::write('taxrule_id_delivery_module', (string) $vatRule->getId());
        ConfigQuery::resetCache();

        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->connection) as $consent) {
            $consent->setActive(0)->save($this->connection);
        }

        $this->getService(ConsentProvider::class)->forgetCache();

        $deliveryModule = (int) ModuleQuery::create()->findOneByCode('CustomDelivery')?->getId();
        $area = (new Area())->setName('Quote concurrency area');
        $area->save($this->connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId(Country::getDefaultCountry()->getId())->save($this->connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($deliveryModule)->save($this->connection);

        $buyer = $this->fixtures->customer($this->fixtures->customerTitle());
        $buyer->setEnable(1)->save($this->connection);
        $this->fixtures->address($buyer, Country::getDefaultCountry())->setIsDefault(1)->save($this->connection);

        $product = $this->fixtures->product($this->fixtures->category(), $vatRule, $this->fixtures->currency(), ['basePrice' => 100.0, 'baseQuantity' => 100, 'title' => 'Concurrent product']);
        $cart = $this->fixtures->cart($buyer);
        $this->fixtures->cartItem($cart, $product, null, ['quantity' => 10.0, 'price' => '100', 'promoPrice' => '100']);

        $quotes = $this->getService(QuoteFacade::class);
        $quote = $quotes->requestFromCart($cart, $buyer, new QuoteRequestInput());
        $lines = array_map(
            static fn ($line): QuoteDraftLine => new QuoteDraftLine((int) $line->getId(), (int) $line->getProductSaleElementsId(), 12.0, '90'),
            [...QuoteItemQuery::create()->filterByQuoteRevisionId(QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByNumber(1)->findOne($this->connection)?->getId())->find($this->connection)],
        );
        $quotes->saveDraft($quote, null, new QuoteDraftInput($lines, '30', '15', $deliveryModule, null, null));
        $quotes->send($quote, null);

        return [$quote, $buyer];
    }

    /**
     * A second cart for the quote, the way the cart of a colleague would be: the lines and the choices of the first.
     */
    private function anotherCartOf(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();
        CartItemTableMap::clearInstancePool();
        $cart = CartQuery::create()->findPk($cart->getId()) ?? throw new \LogicException('The cart is gone.');
        $copy = $cart->copy(true);
        $copy->setToken(bin2hex(random_bytes(16)))->save($this->connection);

        return $copy;
    }

    /**
     * Holds the row of the quote in a transaction, starts the processes, waits until the number of
     * them said is waiting on the quote, and lets them go.
     *
     * @param list<list<string>> $commands
     *
     * @return list<array<string, mixed>> what each process printed
     */
    private function runWhileTheQuoteIsHeld(Quote $quote, array $commands, int $waitingProcesses): array
    {
        $this->connection?->beginTransaction();
        $this->connection?->query('SELECT `id` FROM `quote` WHERE `id` = '.(int) $quote->getId().' FOR UPDATE')->fetchAll();

        $processes = array_map(fn (array $command): mixed => $this->start($command), $commands);
        $this->waitUntilWaitingOnTheQuote($processes, $waitingProcesses);

        $this->connection?->rollBack();

        return array_map(fn (mixed $process): array => $this->waitFor($process), $processes);
    }

    /**
     * @param list<string> $command
     *
     * @return resource
     */
    private function start(array $command): mixed
    {
        $process = proc_open(
            [\PHP_BINARY, __DIR__.'/Support/quote-in-another-process.php', ...$command],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $this->pipes[(int) $process] = $pipes;

        return $process;
    }

    /**
     * Seen twice in a row on the same statement: it is waiting, not passing by. The process that
     * waits on the lock of the acceptance, which the database does not show, is given a moment.
     *
     * @param list<resource> $processes
     */
    private function waitUntilWaitingOnTheQuote(array $processes, int $count): void
    {
        $own = [
            (int) $this->connection?->query('SELECT CONNECTION_ID()')->fetchColumn(),
            (int) $this->otherSession?->query('SELECT CONNECTION_ID()')->fetchColumn(),
        ];
        $waiting = $this->otherSession?->prepare(
            "SELECT ID FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND COMMAND IN ('Query', 'Execute') AND (INFO LIKE '%FROM `quote`%FOR UPDATE%' OR INFO LIKE 'UPDATE `quote`%') AND ID NOT IN (".implode(',', $own).')',
        );
        self::assertNotNull($waiting);
        $deadline = microtime(true) + self::PROCESS_TIMEOUT;
        $seen = [];

        do {
            foreach ($processes as $process) {
                if (!proc_get_status($process)['running']) {
                    self::fail('A process ended before waiting: '.stream_get_contents($this->pipes[(int) $process][2]).stream_get_contents($this->pipes[(int) $process][1]));
                }
            }

            $waiting->execute();
            $threads = $waiting->fetchAll(\PDO::FETCH_COLUMN);
            $waiting->closeCursor();

            if (\count(array_intersect($threads, $seen)) >= $count) {
                usleep(1_500_000);

                return;
            }

            $seen = $threads;
            usleep(200_000);
        } while (microtime(true) < $deadline);

        self::fail('The processes never waited on the quote.');
    }

    /**
     * @param resource $process
     *
     * @return array<string, mixed>
     */
    private function waitFor($process): array
    {
        $stdout = $this->pipes[(int) $process][1];
        $stderr = $this->pipes[(int) $process][2];
        unset($this->pipes[(int) $process]);
        $output = stream_get_contents($stdout);
        $errors = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);
        proc_close($process);

        $start = strrpos((string) $output, '{"action"');
        self::assertNotFalse($start, 'The process printed no answer: '.$errors.$output);

        return json_decode(substr((string) $output, $start), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function stateOf(Quote $quote): string
    {
        QuoteTableMap::clearInstancePool();
        OrderTableMap::clearInstancePool();

        return (string) QuoteQuery::create()->findPk($quote->getId())?->getState();
    }

    private function rememberTheDatabase(): void
    {
        $connection = $this->connection;
        \assert($connection instanceof ConnectionInterface);

        /** @var list<string> $tables */
        $tables = $connection->query("SELECT DISTINCT `TABLE_NAME` FROM information_schema.COLUMNS WHERE `TABLE_SCHEMA` = DATABASE() AND `COLUMN_NAME` = 'id' AND `COLUMN_KEY` = 'PRI'")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->watermarks[$table] = (int) $connection->query('SELECT COALESCE(MAX(`id`), 0) FROM `'.$table.'`')->fetchColumn();
        }

        foreach ($connection->query('SELECT `name`, `value` FROM `config`')->fetchAll(\PDO::FETCH_NUM) as [$name, $value]) {
            $this->configBefore[(string) $name] = null === $value ? null : (string) $value;
        }

        foreach ($connection->query('SELECT `id`, `active` FROM `consent`')->fetchAll(\PDO::FETCH_NUM) as [$id, $active]) {
            $this->consentsBefore[(int) $id] = (int) $active;
        }

        foreach ($connection->query('SELECT `name`, `current_value` FROM `order_sequence`')->fetchAll(\PDO::FETCH_NUM) as [$name, $value]) {
            $this->sequencesBefore[(string) $name] = (int) $value;
        }
    }

    /**
     * Every row the test added is deleted, children before parents (a table that cannot be emptied
     * yet is tried again after the others), then the settings are put back.
     */
    private function takeBackWhatTheTestWrote(): void
    {
        $connection = $this->connection;

        if (!$connection instanceof ConnectionInterface || [] === $this->watermarks) {
            return;
        }

        $pending = array_keys($this->watermarks);

        for ($round = 0; $round < 10 && [] !== $pending; ++$round) {
            $stuck = [];

            foreach ($pending as $table) {
                try {
                    $connection->exec('DELETE FROM `'.$table.'` WHERE `id` > '.$this->watermarks[$table]);
                } catch (\Throwable) {
                    $stuck[] = $table;
                }
            }

            $pending = $stuck;
        }

        if ([] !== $pending) {
            $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($pending as $table) {
                $connection->exec('DELETE FROM `'.$table.'` WHERE `id` > '.$this->watermarks[$table]);
            }

            $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        foreach ($this->configBefore as $name => $value) {
            $statement = $connection->prepare('UPDATE `config` SET `value` = :value WHERE `name` = :name AND NOT (`value` <=> :same)');
            $statement->bindValue(':value', $value);
            $statement->bindValue(':same', $value);
            $statement->bindValue(':name', $name);
            $statement->execute();
        }

        foreach ($this->consentsBefore as $id => $active) {
            $connection->exec('UPDATE `consent` SET `active` = '.$active.' WHERE `id` = '.$id);
        }

        foreach ($this->sequencesBefore as $name => $value) {
            $statement = $connection->prepare('UPDATE `order_sequence` SET `current_value` = :value WHERE `name` = :name');
            $statement->bindValue(':value', $value, \PDO::PARAM_INT);
            $statement->bindValue(':name', $name);
            $statement->execute();
        }

        $known = implode(',', array_map(static fn (string $name): string => "'".str_replace("'", "''", $name)."'", array_keys($this->sequencesBefore)));
        $connection->exec('DELETE FROM `order_sequence` WHERE '.('' === $known ? '1 = 1' : '`name` NOT IN ('.$known.')'));

        ConfigQuery::resetCache();
        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(QuoteTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(QuoteTableMap::DATABASE_NAME),
        );
    }
}
