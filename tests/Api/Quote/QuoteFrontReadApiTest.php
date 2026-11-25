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

use Thelia\Domain\Quote\DTO\QuoteDraftInput;

/**
 * The quotes as the storefront reads them: the account's own, one by one without their revisions, which
 * are read apart with their lines, and nothing of the shop's draft or notes.
 */
final class QuoteFrontReadApiTest extends QuoteApiTestCase
{
    public function testABuyerReadsTheirQuoteWithoutItsRevisions(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];

        $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $this->token($scenario['buyer']));

        self::assertJsonResponseSuccessful($response);
        $body = $this->body($response);
        self::assertSame($quote->getRef(), $body['ref']);
        self::assertSame('quoted', $body['state']);
        self::assertSame(2, $body['currentRevision']);
        self::assertSame('1620.000000', $body['totalTaxed']);
        self::assertArrayHasKey('reverseCharge', $body);
        self::assertArrayNotHasKey('revisions', $body);
    }

    public function testABuyerReadsTheRevisionsThatWereSentAsASummary(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];

        $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions', token: $this->token($scenario['buyer']));

        self::assertJsonResponseSuccessful($response);
        $members = $this->body($response)['hydra:member'];
        self::assertSame([1, 2], array_column($members, 'number'));
        self::assertSame(['customer', 'admin'], array_column($members, 'author'));
        self::assertSame('/api/front/account/quotes/'.$quote->getId().'/revisions/2', $members[1]['@id']);
        self::assertSame('1620.000000', $members[1]['totalTaxed']);
        $keys = array_keys($members[1]);
        sort($keys);
        self::assertSame(['@id', '@type', 'author', 'number', 'reverseCharge', 'sentAt', 'totalTaxed', 'totalUntaxed', 'validUntil'], $keys, 'A summary: no line, no note, no address.');
    }

    public function testABuyerReadsOneRevisionWithItsLines(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];

        $response = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions/2', token: $this->token($scenario['buyer']));

        self::assertJsonResponseSuccessful($response);
        $answer = $this->body($response);
        self::assertSame(2, $answer['number']);
        self::assertSame('1620.000000', $answer['totalTaxed']);
        self::assertSame('/api/front/account/quotes/'.$quote->getId().'/revisions/2', $answer['@id']);
        self::assertCount(3, $answer['items']);
        self::assertSame('90.000000', $answer['items'][0]['price']);
        self::assertSame('100.000000', $answer['items'][0]['catalogPrice']);
    }

    public function testTheDraftOfTheShopIsNeverReadFromTheStorefront(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        // A quote the buyer can still answer takes no draft: it is past its validity for the shop to write one.
        $quote->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput([], '0', '0', null, 'Internal thoughts'));
        $token = $this->token($scenario['buyer']);

        $list = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions', token: $token);
        self::assertSame([1, 2], array_column($this->body($list)['hydra:member'], 'number'));
        self::assertStringNotContainsString('Internal thoughts', (string) $list->getContent());

        $draft = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions/3', token: $token);
        self::assertSame(404, $draft->getStatusCode());
        self::assertStringNotContainsString('Internal thoughts', (string) $draft->getContent());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().'/revisions/9', token: $token)->getStatusCode());
    }

    public function testTheBuyerNeverReadsWhoOfTheShopAnsweredNorWhenItRemindedThem(): void
    {
        $admin = $this->createFixtureFactory()->admin(['password' => 'password']);
        $scenario = $this->quotedQuote(null, $admin);
        $quote = $scenario['quote'];
        $this->quotes->remind($quote);

        $buyer = $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $this->token($scenario['buyer']));
        self::assertJsonResponseSuccessful($buyer);
        $seen = $this->body($buyer);
        self::assertStringNotContainsString('adminId', (string) $buyer->getContent());
        self::assertArrayNotHasKey('remindedAt', $seen);
        self::assertArrayNotHasKey('customerId', $seen);

        foreach (['/revisions', '/revisions/2'] as $suffix) {
            self::assertStringNotContainsString('adminId', (string) $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId().$suffix, token: $this->token($scenario['buyer']))->getContent());
        }

        $shop = $this->body($this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId(), token: $this->authenticateAsAdmin($admin)));
        self::assertSame((int) $admin->getId(), $shop['revisions'][1]['adminId']);
        self::assertArrayHasKey('remindedAt', $shop);
        self::assertSame((int) $scenario['buyer']->getId(), $shop['customerId']);
    }

    public function testTheStateFilterOfTheListNarrowsTheQuotesOfTheBuyer(): void
    {
        $buyer = $this->customer();
        $waiting = $this->quotedQuote($buyer)['quote'];
        $turnedDown = $this->quotedQuote($buyer)['quote'];
        $this->quotes->reject($turnedDown, $buyer, 2);
        $late = $this->quotedQuote($buyer)['quote'];
        $late->setValidUntil((new \DateTimeImmutable('-1 day'))->format('Y-m-d'))->save($this->getPropelConnection());
        $token = $this->token($buyer);

        foreach (['quoted' => $waiting, 'rejected' => $turnedDown, 'expired' => $late] as $state => $expected) {
            $response = $this->jsonRequest('GET', '/api/front/account/quotes?state='.$state, token: $token);

            self::assertJsonResponseSuccessful($response);
            self::assertSame([(int) $expected->getId()], $this->memberIds($response), 'state='.$state);
        }

        self::assertCount(3, $this->memberIds($this->jsonRequest('GET', '/api/front/account/quotes', token: $token)), 'Without the filter, all of them.');
    }

    public function testTheListCarriesNoLines(): void
    {
        $scenario = $this->quotedQuote();

        $response = $this->jsonRequest('GET', '/api/front/account/quotes', token: $this->token($scenario['buyer']));

        self::assertJsonResponseSuccessful($response);
        self::assertSame([(int) $scenario['quote']->getId()], $this->memberIds($response));
        self::assertArrayNotHasKey('revisions', $this->body($response)['hydra:member'][0]);
    }
}
