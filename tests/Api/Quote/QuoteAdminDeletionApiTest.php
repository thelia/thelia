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

use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Model\Admin;
use Thelia\Model\Country;
use Thelia\Model\Map\QuoteItemTableMap;
use Thelia\Model\Map\QuoteRevisionTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\OrderStatus;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteItemRequest;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevisionAddressQuery;
use Thelia\Model\QuoteRevisionQuery;

/**
 * Deleting a quote: only one the shop never sent and no order came from, with everything it holds,
 * and for a profile that holds the right to delete quotes. What the shop sent is what it offered,
 * and stays.
 */
final class QuoteAdminDeletionApiTest extends QuoteApiTestCase
{
    public function testAQuoteTheShopNeverSentIsDeletedWithEverythingItHolds(): void
    {
        $buyer = $this->customer();
        $this->fixtures->address($buyer, Country::getDefaultCountry())->setIsDefault(1)->save($this->getPropelConnection());
        $scenario = $this->requestedQuote($buyer);
        $quote = $scenario['quote'];
        $other = $this->requestedQuote()['quote'];
        $this->quotes->saveDraft($quote, null, new QuoteDraftInput([new QuoteDraftLine(null, $this->saleElementId($scenario['c']), 2.0, '19')]));
        $firstLine = QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber(1)->endUse()->findOne();
        (new QuoteItemRequest())->setQuoteItemId($firstLine->getId())->setQuantity(3.0)->save($this->getPropelConnection());
        $revisionIds = array_map('intval', QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->select('Id')->find()->toArray());
        self::assertCount(2, $revisionIds);
        self::assertGreaterThan(0, QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($revisionIds)->count(), 'the request froze the addresses of the buyer');

        $response = $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json');

        self::assertSame(204, $response->getStatusCode());
        $this->forgetPooledRows();
        self::assertNull(QuoteQuery::create()->findPk($quote->getId()));
        self::assertSame(0, QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count());
        self::assertSame(0, QuoteItemQuery::create()->filterByQuoteRevisionId($revisionIds)->count());
        self::assertSame(0, QuoteItemRequestQuery::create()->filterByQuoteItemId($firstLine->getId())->count());
        self::assertSame(0, QuoteRevisionAddressQuery::create()->filterByQuoteRevisionId($revisionIds)->count());
        self::assertNotNull(QuoteQuery::create()->findPk($other->getId()), 'another quote is left as it was');
        self::assertSame(2, QuoteItemQuery::create()->useQuoteRevisionQuery()->filterByQuoteId($other->getId())->endUse()->count());
        self::assertSame(404, $this->jsonRequest('GET', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken())->getStatusCode());
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json')->getStatusCode(), 'there is nothing left to delete');
    }

    public function testAQuoteTheShopStartedForACustomerIsDeletedBeforeItIsSent(): void
    {
        $quote = $this->quotes->createForCustomer($this->customer(), null);

        self::assertSame(204, $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json')->getStatusCode());
        $this->forgetPooledRows();
        self::assertNull(QuoteQuery::create()->findPk($quote->getId()));
    }

    public function testAQuoteTheShopSentIsNeverDeleted(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];

        $response = $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('deletion_refused_sent', $this->reason($response));
        $this->assertWhole($quote, 2);

        // Whatever the buyer made of it afterwards, it is still what was offered.
        $this->quotes->reject($quote, $scenario['buyer'], 2);
        $again = $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json');

        self::assertSame(409, $again->getStatusCode());
        self::assertSame('deletion_refused_sent', $this->reason($again));
        $this->assertWhole($quote, 2);
    }

    public function testAQuoteThatAnOrderCameFromIsNeverDeleted(): void
    {
        $scenario = $this->requestedQuote();
        $quote = $scenario['quote'];

        foreach ([OrderStatus::CODE_NOT_PAID, OrderStatus::CODE_CANCELED] as $status) {
            $order = $this->fixtures->order($scenario['buyer'], ['statusCode' => $status]);
            $order->setQuoteId($quote->getId())->save($this->getPropelConnection());

            $response = $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json');

            self::assertSame(409, $response->getStatusCode(), $status);
            self::assertSame('deletion_refused_ordered', $this->reason($response), $status.': a cancelled order still names its quote');
            $this->assertWhole($quote, 1);
            $order->setQuoteId(null)->save($this->getPropelConnection());
        }
    }

    public function testEachRefusalLeavesTheQuoteAsItWas(): void
    {
        $scenario = $this->quotedQuote();
        $quote = $scenario['quote'];
        $this->forgetPooledRows();
        $before = QuoteQuery::create()->findPk($quote->getId())->toArray();

        $this->jsonRequest('DELETE', '/api/admin/quotes/'.$quote->getId(), token: $this->adminToken(), format: 'json');

        $this->forgetPooledRows();
        self::assertSame($before, QuoteQuery::create()->findPk($quote->getId())->toArray());
    }

    public function testDeletingAskedForTheRightToDelete(): void
    {
        $quote = $this->requestedQuote()['quote'];
        $uri = '/api/admin/quotes/'.$quote->getId();

        foreach ([[AccessManager::VIEW], [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE]] as $accesses) {
            $token = $this->authenticateAsAdmin($this->profileWith($accesses));
            self::assertSame(403, $this->jsonRequest('DELETE', $uri, token: $token, format: 'json')->getStatusCode(), implode(',', $accesses));
        }

        $other = $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([AdminResources::PRODUCT => [AccessManager::DELETE]]));
        self::assertSame(403, $this->jsonRequest('DELETE', $uri, token: $other, format: 'json')->getStatusCode(), 'the right to delete something else');
        self::assertSame(401, $this->jsonRequest('DELETE', $uri, format: 'json')->getStatusCode(), 'anonymous');
        self::assertContains($this->jsonRequest('DELETE', $uri, token: $this->token($this->customer()), format: 'json')->getStatusCode(), [401, 403], 'a customer');
        $this->assertWhole($quote, 1);

        $deleter = $this->authenticateAsAdmin($this->profileWith([AccessManager::DELETE]));
        self::assertSame(204, $this->jsonRequest('DELETE', $uri, token: $deleter, format: 'json')->getStatusCode());
    }

    public function testAQuoteThatDoesNotExistIsNotFound(): void
    {
        self::assertSame(404, $this->jsonRequest('DELETE', '/api/admin/quotes/987654321', token: $this->adminToken(), format: 'json')->getStatusCode());
    }

    private function assertWhole(Quote $quote, int $revisions): void
    {
        $this->forgetPooledRows();
        self::assertNotNull(QuoteQuery::create()->findPk($quote->getId()), 'the quote is still there');
        self::assertSame($revisions + ($this->hasDraft($quote) ? 1 : 0), QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->count(), 'and so are its revisions');
    }

    private function hasDraft(Quote $quote): bool
    {
        return QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->filterByState('draft')->count() > 0;
    }

    private function forgetPooledRows(): void
    {
        QuoteTableMap::clearInstancePool();
        QuoteRevisionTableMap::clearInstancePool();
        QuoteItemTableMap::clearInstancePool();
    }

    /**
     * @param list<string> $accesses
     */
    private function profileWith(array $accesses): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([AdminResources::QUOTE => $accesses]);
    }
}
