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

use ApiPlatform\Metadata\Exception\InvalidArgumentException as ApiPlatformInvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Thelia\Domain\Quote\Cart\QuoteCartLockedException;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Exception\CounterOfferRefusedException;
use Thelia\Domain\Quote\Exception\QuoteActionRefusedException;
use Thelia\Domain\Quote\Exception\QuoteAlreadyOrderedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteRequestRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CartQuery;

/**
 * The status each refusal of the quotes answers, and that the two answers the API already gave for a
 * body it cannot read stay what they were: setting the mapping replaces the defaults of the bundle.
 */
final class QuoteErrorMappingApiTest extends QuoteApiTestCase
{
    #[DataProvider('refusals')]
    public function testEachRefusalAnswersItsStatus(\Throwable $refusal, int $status): void
    {
        self::assertSame($status, $this->statusOf($refusal), $refusal::class);
    }

    /**
     * @return iterable<string, array{0: \Throwable, 1: int}>
     */
    public static function refusals(): iterable
    {
        yield 'an unknown quote' => [UnknownQuoteException::withId(1), 404];
        yield 'a transition the state does not allow' => [QuoteTransitionRefusedException::notAllowed(QuoteState::Rejected, QuoteState::Accepted), 409];
        yield 'a draft the state does not take' => [QuoteTransitionRefusedException::cannotBeDrafted(QuoteState::Quoted), 409];
        yield 'a cart whose prices are settled' => [QuoteCartLockedException::linesAreFrozen(), 409];
        yield 'a quote ordered twice' => [new QuoteAlreadyOrderedException(), 409];
        yield 'lines the shop no longer sells' => [new QuoteLinesUnavailableException([['reference' => 'A', 'title' => 'A']]), 422];
        yield 'a currency the shop no longer takes' => [new QuoteCurrencyUnavailableException(), 422];
        yield 'a request the shop refuses' => [QuoteRequestRefusedException::quotesAreDisabled(), 422];
        yield 'a draft the shop refuses' => [QuoteDraftRefusedException::noLines(), 422];
        yield 'a counter-proposal the shop refuses' => [CounterOfferRefusedException::nothingAsked(), 422];
        yield 'an answer the role does not allow' => [QuoteActionRefusedException::revisionIsNotTheCurrentOne(), 422];
    }

    public function testTheTwoAnswersOfTheBundleThatTheMappingReplacedAreStillThere(): void
    {
        self::assertSame(400, $this->statusOf(new NotEncodableValueException('x')));
        self::assertSame(400, $this->statusOf(new ApiPlatformInvalidArgumentException('x')));
    }

    public function testACartOfAQuoteRefusesTheChangeOfItsLinesAsAConflictWithItsReason(): void
    {
        $scenario = $this->quotedQuote();
        $buyer = $scenario['buyer'];
        $this->jsonRequest('POST', '/api/front/account/quotes/'.$scenario['quote']->getId().'/accept', ['revision' => 2], $this->token($buyer), 'json');
        $cart = CartQuery::create()->filterByQuoteId($scenario['quote']->getId())->findOne();
        $line = CartItemQuery::create()->filterByCartId($cart->getId())->findOne();

        $response = $this->jsonRequest('PUT', '/api/front/cart_items/'.$line->getId(), ['quantity' => 99], $this->token($buyer), 'json');

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('quote_cart_locked', $this->reason($response));
        self::assertSame(12.0, (float) CartItemQuery::create()->findPk($line->getId())->getQuantity());
    }

    private function statusOf(\Throwable $exception): int
    {
        /** @var array<class-string, int> $mapping */
        $mapping = static::getContainer()->getParameter('api_platform.exception_to_status');

        foreach ($mapping as $class => $status) {
            if (is_a($exception, $class, true)) {
                return $status;
            }
        }

        return 500;
    }
}
