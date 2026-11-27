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

use PHPUnit\Framework\Attributes\DataProvider;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\CompanyRoleCode;
use Thelia\Model\CartQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemRequestQuery;
use Thelia\Model\QuoteQuery;

/**
 * A quote is reachable by its owner and, for a buyer of a company, by what the role says: the quotes of
 * the company are read with the right to read the orders of the company and answered with the right to
 * ask for quotes. Nothing of a quote is reached from another account or another company: the reads and
 * every transition answer "not found", and the quote does not move. Each case is tried both ways.
 */
final class QuoteIsolationApiTest extends QuoteApiTestCase
{
    public function testAnAccountReadsNothingOfTheQuoteOfAnother(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        foreach ([[$first, $second], [$second, $first]] as [$mine, $theirs]) {
            $token = $this->token($mine['buyer']);

            self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$theirs['quote']->getId(), token: $token)->getStatusCode());
            self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$mine['quote']->getId(), token: $token)->getStatusCode());

            $list = $this->jsonRequest('GET', '/api/front/account/quotes', token: $token);
            self::assertSame([(int) $mine['quote']->getId()], $this->memberIds($list));
        }
    }

    public function testAnAccountReadsNoRevisionOfTheQuoteOfAnother(): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        foreach ([[$first, $second], [$second, $first]] as [$mine, $theirs]) {
            $token = $this->token($mine['buyer']);

            foreach (['/revisions', '/revisions/1', '/revisions/2'] as $suffix) {
                $other = $this->jsonRequest('GET', '/api/front/account/quotes/'.$theirs['quote']->getId().$suffix, token: $token);

                self::assertSame(404, $other->getStatusCode(), $suffix);
                self::assertStringNotContainsString((string) $theirs['quote']->getRef(), (string) $other->getContent());
                self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$mine['quote']->getId().$suffix, token: $token)->getStatusCode(), $suffix);
            }
        }
    }

    public function testACompanyReadsTheRevisionsOfItsQuotesAndNoOtherCompanyDoes(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $firstAdministrator = $this->buyerOf($first, CompanyRoleCode::ADMINISTRATOR);
        $secondAdministrator = $this->buyerOf($second, CompanyRoleCode::ADMINISTRATOR);
        $ofFirst = $this->quotedQuote($this->buyerOf($first))['quote'];
        $ofSecond = $this->quotedQuote($this->buyerOf($second))['quote'];

        foreach ([[$firstAdministrator, $ofFirst, $ofSecond], [$secondAdministrator, $ofSecond, $ofFirst]] as [$administrator, $mine, $theirs]) {
            $token = $this->token($administrator);

            foreach (['/revisions', '/revisions/2'] as $suffix) {
                self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$mine->getId().$suffix, token: $token)->getStatusCode(), $suffix);
                self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$theirs->getId().$suffix, token: $token)->getStatusCode(), $suffix);
            }
        }
    }

    public function testAColleagueWhoDoesNotReadTheOrdersOfTheCompanyReadsNoRevisionOfTheQuotesOfAnother(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $colleague = $this->buyerOf($company);
        $mine = $this->quotedQuote($buyer)['quote'];
        $theirs = $this->quotedQuote($colleague)['quote'];

        foreach ([[$buyer, $mine, $theirs], [$colleague, $theirs, $mine]] as [$who, $own, $other]) {
            $token = $this->token($who);

            self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$own->getId().'/revisions/2', token: $token)->getStatusCode());
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$other->getId().'/revisions/2', token: $token)->getStatusCode());
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$other->getId().'/revisions', token: $token)->getStatusCode());
        }
    }

    #[DataProvider('transitions')]
    public function testAnAccountCannotMoveTheQuoteOfAnother(string $path, array $body): void
    {
        $first = $this->quotedQuote();
        $second = $this->quotedQuote();

        foreach ([[$first, $second], [$second, $first]] as [$mine, $theirs]) {
            $quote = $theirs['quote'];
            $payload = $this->payloadFor($body, $quote);

            $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().$path, $payload, $this->token($mine['buyer']), 'json');

            self::assertSame(404, $response->getStatusCode(), $path);
            self::assertSame('quoted', $this->stateOf($quote), 'The quote did not move.');
            self::assertSame(0, QuoteItemRequestQuery::create()->count(), 'No counter-proposal was written.');
            self::assertSame(0, $this->quoteCartsOf($quote), 'No cart was built for it.');
        }
    }

    public function testAnAccountCannotAskForAQuoteFromTheCartOfAnother(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $cart = $this->cartOf($owner, [[$this->product('100', 'A'), 1.0, '100']]);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()], $this->token($other), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('cart_not_owned', $this->reason($response));
        self::assertSame(0, QuoteQuery::create()->count());
    }

    public function testACartThatDoesNotExistAnswersAsTheCartOfAnother(): void
    {
        $response = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => 987654321], $this->token($this->customer()), 'json');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('cart_not_owned', $this->reason($response));
    }

    public function testACompanyReadsTheQuotesOfItsBuyersAndNoOtherCompanyDoes(): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $firstAdministrator = $this->buyerOf($first, CompanyRoleCode::ADMINISTRATOR);
        $secondAdministrator = $this->buyerOf($second, CompanyRoleCode::ADMINISTRATOR);
        $firstBuyer = $this->buyerOf($first);
        $secondBuyer = $this->buyerOf($second);
        $ofFirst = $this->quotedQuote($firstBuyer)['quote'];
        $ofSecond = $this->quotedQuote($secondBuyer)['quote'];
        self::assertSame((int) $first->getId(), $ofFirst->getCompanyId());

        foreach ([[$firstAdministrator, $ofFirst, $ofSecond], [$secondAdministrator, $ofSecond, $ofFirst]] as [$administrator, $mine, $theirs]) {
            $token = $this->token($administrator);

            self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$mine->getId(), token: $token)->getStatusCode());
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$theirs->getId(), token: $token)->getStatusCode());
            self::assertSame([(int) $mine->getId()], $this->memberIds($this->jsonRequest('GET', '/api/front/account/quotes', token: $token)));
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('transitions')]
    public function testACompanyCannotMoveTheQuoteOfAnotherCompany(string $path, array $body): void
    {
        $first = $this->company('Acme');
        $second = $this->company('Globex');
        $firstAdministrator = $this->buyerOf($first, CompanyRoleCode::ADMINISTRATOR);
        $secondAdministrator = $this->buyerOf($second, CompanyRoleCode::ADMINISTRATOR);
        $ofFirst = $this->quotedQuote($this->buyerOf($first))['quote'];
        $ofSecond = $this->quotedQuote($this->buyerOf($second))['quote'];

        foreach ([[$firstAdministrator, $ofSecond], [$secondAdministrator, $ofFirst]] as [$administrator, $theirs]) {
            $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$theirs->getId().$path, $this->payloadFor($body, $theirs), $this->token($administrator), 'json');

            self::assertSame(404, $response->getStatusCode(), $path);
            self::assertSame('quoted', $this->stateOf($theirs));
            self::assertSame(0, $this->quoteCartsOf($theirs));
        }
    }

    public function testABuyerWhoDoesNotReadTheOrdersOfTheCompanyReadsOnlyTheirOwnQuotes(): void
    {
        $company = $this->company();
        $buyer = $this->buyerOf($company);
        $colleague = $this->buyerOf($company);
        $mine = $this->quotedQuote($buyer)['quote'];
        $theirs = $this->quotedQuote($colleague)['quote'];

        foreach ([[$buyer, $mine, $theirs], [$colleague, $theirs, $mine]] as [$who, $own, $other]) {
            $token = $this->token($who);

            self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$own->getId(), token: $token)->getStatusCode());
            self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$other->getId(), token: $token)->getStatusCode());
            self::assertSame([(int) $own->getId()], $this->memberIds($this->jsonRequest('GET', '/api/front/account/quotes', token: $token)));
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('transitions')]
    public function testAColleagueWhoReadsTheQuotesOfTheCompanyWithoutTheRightToAnswerCannotMoveThem(string $path, array $body): void
    {
        $company = $this->company();
        $approver = $this->buyerOf($company, CompanyRoleCode::APPROVER);
        $quote = $this->quotedQuote($this->buyerOf($company))['quote'];
        $token = $this->token($approver);

        self::assertSame(200, $this->jsonRequest('GET', '/api/front/account/quotes/'.$quote->getId(), token: $token)->getStatusCode());

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().$path, $this->payloadFor($body, $quote), $token, 'json');

        self::assertSame(403, $response->getStatusCode(), $path);
        self::assertSame('quoted', $this->stateOf($quote));
        self::assertSame(0, $this->quoteCartsOf($quote));
    }

    public function testARoleThatCannotPlaceOrdersAnswersButDoesNotAccept(): void
    {
        $company = $this->company();
        $role = $this->roleOf('quote_only', $company, [CompanyPermission::QUOTE_REQUEST, CompanyPermission::ORDER_VIEW_OWN]);
        $buyer = $this->buyerOf($company, $role->getCode());
        $quote = $this->quotedQuote($buyer)['quote'];

        $response = $this->jsonRequest('POST', '/api/front/account/quotes/'.$quote->getId().'/accept', ['revision' => 2], $this->token($buyer), 'json');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('quoted', $this->stateOf($quote));
        self::assertSame(0, $this->quoteCartsOf($quote));
    }

    public function testAnAccountThatIsNotAllowedToAskForQuotesIsRefusedTheRequest(): void
    {
        $company = $this->company();
        $approver = $this->buyerOf($company, CompanyRoleCode::APPROVER);
        $cart = $this->cartOf($approver, [[$this->product('100', 'A'), 1.0, '100']]);

        $response = $this->jsonRequest('POST', '/api/front/account/quotes', ['cartId' => $cart->getId()], $this->token($approver), 'json');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, QuoteQuery::create()->count());
    }

    public function testTheDraftOfTheShopAndTheQuoteItHasNotSentYetAreInvisibleToTheBuyer(): void
    {
        $buyer = $this->customer();
        $draft = $this->quotes->createForCustomer($buyer, null);
        $token = $this->token($buyer);

        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$draft->getId(), token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$draft->getId().'/revisions', token: $token)->getStatusCode());
        self::assertSame(404, $this->jsonRequest('GET', '/api/front/account/quotes/'.$draft->getId().'/revisions/1', token: $token)->getStatusCode());
        self::assertSame([], $this->memberIds($this->jsonRequest('GET', '/api/front/account/quotes', token: $token)));
        self::assertSame(404, $this->jsonRequest('POST', '/api/front/account/quotes/'.$draft->getId().'/reject', ['revision' => 1], $token, 'json')->getStatusCode());
        self::assertSame('draft', $this->stateOf($draft));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function transitions(): iterable
    {
        yield 'counter-offer' => ['/counter-offer', ['revision' => 2, 'lines' => [['quoteItemId' => '{line}', 'price' => '80']]]];
        yield 'accept' => ['/accept', ['revision' => 2]];
        yield 'reject' => ['/reject', ['revision' => 2]];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function payloadFor(array $body, Quote $quote): array
    {
        if (!isset($body['lines'])) {
            return $body;
        }

        $line = \Thelia\Model\QuoteItemQuery::create()
            ->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->filterByNumber(2)->endUse()
            ->findOne();
        $body['lines'][0]['quoteItemId'] = (int) $line->getId();

        return $body;
    }

    private function stateOf(Quote $quote): string
    {
        \Thelia\Model\Map\QuoteTableMap::clearInstancePool();

        return (string) QuoteQuery::create()->findPk($quote->getId())->getState();
    }

    private function quoteCartsOf(Quote $quote): int
    {
        return CartQuery::create()->filterByQuoteId($quote->getId())->count();
    }
}
