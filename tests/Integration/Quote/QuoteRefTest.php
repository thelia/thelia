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

namespace Thelia\Tests\Integration\Quote;

use Thelia\Domain\Quote\Service\QuoteRefAllocator;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;

/**
 * The reference of a quote: its own series, its own format, never the reference of an order.
 */
final class QuoteRefTest extends QuoteDomainTestCase
{
    public function testTheQuotesAreNumberedFromTheirOwnSeriesWithoutAGap(): void
    {
        $refs = [];

        foreach ([$this->request($this->customer()), $this->quotes->createForCustomer($this->customer(), null), $this->request($this->customer())] as $quote) {
            $refs[] = (string) $quote->getRef();
        }

        $numbers = array_map(static fn (string $ref): int => (int) substr($ref, 3), $refs);

        self::assertSame([$numbers[0], $numbers[0] + 1, $numbers[0] + 2], $numbers);

        foreach ($refs as $ref) {
            self::assertMatchesRegularExpression('/^QUO\d{8}$/', $ref);
            self::assertFalse(OrderQuery::create()->filterByRef($ref)->exists(), 'never the reference of an order');
        }

        self::assertSame(\count($refs), \count(array_unique($refs)));
    }

    public function testTheOrdersAreNotNumberedByTheSeriesOfTheQuotes(): void
    {
        $series = $this->getService(GaplessSequenceGenerator::class);
        $connection = $this->getPropelConnection();
        $series->set('order_ref', 500, $connection);
        $series->set(QuoteRefAllocator::SEQUENCE_NAME, 20, $connection);

        $quote = $this->request($this->customer());

        self::assertSame('QUO00000021', $quote->getRef());
        self::assertSame(
            500,
            (int) $connection->query("SELECT `current_value` FROM `order_sequence` WHERE `name` = 'order_ref'")->fetchColumn(),
            'the series of the orders did not move',
        );
    }

    public function testTheFormatIsTheOneOfTheShop(): void
    {
        ConfigQuery::write('quote_ref_format', 'DEV-%05d');
        ConfigQuery::resetCache();

        $quote = $this->request($this->customer());

        self::assertMatchesRegularExpression('/^DEV-\d{5}$/', (string) $quote->getRef());
    }

    public function testAFormatThatIsNotAnumberedPatternFallsBackToTheDefaultOne(): void
    {
        $allocator = $this->getService(QuoteRefAllocator::class);

        foreach (['', 'QUOTE', '%s', '%d-%d', '100%', '%08d%d', '%\'x8d'] as $format) {
            ConfigQuery::write('quote_ref_format', $format);
            ConfigQuery::resetCache();

            self::assertSame('QUO00000007', $allocator->format(7), 'format "'.$format.'"');
        }

        ConfigQuery::write('quote_ref_format', 'Q%d');
        ConfigQuery::resetCache();
        self::assertSame('Q7', $allocator->format(7));
    }

    public function testAReferenceAnOrderAlreadyHoldsIsNeverGiven(): void
    {
        $customer = $this->customer();
        $this->getService(GaplessSequenceGenerator::class)->set(QuoteRefAllocator::SEQUENCE_NAME, 40, $this->getPropelConnection());
        // The format of the shop is one the orders could also use.
        ConfigQuery::write('quote_ref_format', 'ORD%012d');
        ConfigQuery::resetCache();
        $order = $this->fixtures->order($customer);
        $order->setRef('ORD000000000041')->save($this->getPropelConnection());

        $quote = $this->request($customer);

        self::assertSame('ORD000000000042', $quote->getRef(), 'the number 41 is skipped, it belongs to an order');
        self::assertFalse(OrderQuery::create()->filterByRef((string) $quote->getRef())->exists());
    }
}
