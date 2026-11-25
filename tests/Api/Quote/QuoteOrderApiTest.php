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

namespace Thelia\Tests\Api\Quote;

use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderStatus;
use Thelia\Model\Quote;
use Thelia\Test\Trait\RecordsSqlQueries;

/**
 * The order a quote became, read with the quote by the shop and by the buyer: the order that names
 * the quote and was not cancelled, in a number of statements that does not grow with the list.
 */
final class QuoteOrderApiTest extends QuoteApiTestCase
{
    use RecordsSqlQueries;

    public function testTheQuoteSaysTheOrderItBecameToTheShopAndToTheBuyer(): void
    {
        $scenario = $this->quotedQuote();
        $order = $this->liveOrderOf($scenario['quote'], $scenario['buyer']);

        $admin = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$scenario['quote']->getId(), token: $this->adminToken()));
        $front = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$scenario['quote']->getId(), token: $this->token($scenario['buyer'])));

        foreach ([$admin, $front] as $body) {
            self::assertSame((int) $order->getId(), $body['orderId']);
            self::assertSame($order->getRef(), $body['orderRef']);
        }
    }

    public function testAQuoteWithNoOrderSaysNone(): void
    {
        $scenario = $this->quotedQuote();

        $front = $this->body($this->jsonRequest('GET', '/api/front/account/quotes/'.$scenario['quote']->getId(), token: $this->token($scenario['buyer'])));

        self::assertNull($front['orderId'] ?? null);
        self::assertNull($front['orderRef'] ?? null);
    }

    public function testAnOrderThatWasCancelledIsNotTheOrderOfTheQuote(): void
    {
        $scenario = $this->quotedQuote();
        $this->liveOrderOf($scenario['quote'], $scenario['buyer'], OrderStatus::CODE_CANCELED);

        $admin = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$scenario['quote']->getId(), token: $this->adminToken()));
        $front = $this->body($this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer'])));

        self::assertNull($admin['orderId'] ?? null);
        self::assertNull($front['hydra:member'][0]['orderId'] ?? null);
        self::assertNull($front['hydra:member'][0]['orderRef'] ?? null);
    }

    public function testTheListCarriesTheOrderOfEachQuoteInTheSameStatementsWhateverItsLength(): void
    {
        $counts = [];
        $orders = [];

        foreach ([1, 5] as $quotes) {
            $buyer = $this->customer();

            for ($quote = 0; $quote < $quotes; ++$quote) {
                $scenario = $this->quotedQuote($buyer);
                $orders[(int) $scenario['quote']->getId()] = $this->liveOrderOf($scenario['quote'], $buyer);
            }

            $token = $this->token($buyer);
            $response = null;
            $statements = $this->recordSqlQueries(function () use (&$response, $token): void {
                $response = $this->jsonRequest('GET', '/api/front/account/quotes', token: $token);
            });

            self::assertJsonResponseSuccessful($response);
            self::assertCount($quotes, $this->body($response)['hydra:member']);

            foreach ($this->body($response)['hydra:member'] as $member) {
                self::assertSame((int) $orders[$member['id']]->getId(), $member['orderId']);
                self::assertSame($orders[$member['id']]->getRef(), $member['orderRef']);
            }

            $counts[$quotes] = \count($statements);
            self::assertSame(0, self::countSqlQueriesSelectingFrom(array_values(array_filter($statements, static fn (string $statement): bool => !str_contains($statement, 'FROM `quote`'))), 'order'), 'the orders are read with the quotes');
        }

        self::assertSame($counts[1], $counts[5], 'a quote costs no statement');
    }

    private function liveOrderOf(Quote $quote, Customer $buyer, string $statusCode = OrderStatus::CODE_NOT_PAID): Order
    {
        $order = $this->fixtures->order($buyer, ['statusCode' => $statusCode]);
        $order->setQuoteId($quote->getId())->save($this->getPropelConnection());

        return $order;
    }
}
