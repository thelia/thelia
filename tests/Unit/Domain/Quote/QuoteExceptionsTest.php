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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Exception\CounterOfferRefusedException;
use Thelia\Domain\Quote\Exception\QuoteActionRefusedException;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\Exception\QuoteException;
use Thelia\Domain\Quote\Exception\QuoteRequestRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;

/**
 * Every refusal has a reason that does not depend on the language, and a message the catalogs
 * of the core translate.
 */
final class QuoteExceptionsTest extends TestCase
{
    /**
     * @return list<QuoteException>
     */
    private static function everyRefusal(): array
    {
        $refusals = [UnknownQuoteException::withId(1)];

        foreach ([
            QuoteTransitionRefusedException::class => [
                static fn (): QuoteTransitionRefusedException => QuoteTransitionRefusedException::notAllowed(QuoteState::Quoted, QuoteState::Ordered),
                static fn (): QuoteTransitionRefusedException => QuoteTransitionRefusedException::cannotBeDrafted(QuoteState::Quoted),
                static fn (): QuoteTransitionRefusedException => QuoteTransitionRefusedException::cannotBeDeletedOnceSent(QuoteState::Quoted),
                static fn (): QuoteTransitionRefusedException => QuoteTransitionRefusedException::cannotBeDeletedOnceOrdered(QuoteState::Ordered),
            ],
            QuoteRequestRefusedException::class => [
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::quotesAreDisabled(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::emptyCart(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::notPermitted(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::cartNotOwned(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::invalidLines([1]),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::addressRefused(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::neededByPast(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::cartOfAQuote(),
                static fn (): QuoteRequestRefusedException => QuoteRequestRefusedException::companySuspended(),
            ],
            QuoteActionRefusedException::class => [
                static fn (): QuoteActionRefusedException => QuoteActionRefusedException::notPermitted(),
                static fn (): QuoteActionRefusedException => QuoteActionRefusedException::revisionIsNotTheCurrentOne(),
                static fn (): QuoteActionRefusedException => QuoteActionRefusedException::companySuspended(),
            ],
            CounterOfferRefusedException::class => [
                static fn (): CounterOfferRefusedException => CounterOfferRefusedException::nothingAsked(),
                static fn (): CounterOfferRefusedException => CounterOfferRefusedException::unknownLine(),
                static fn (): CounterOfferRefusedException => CounterOfferRefusedException::duplicateLine(),
                static fn (): CounterOfferRefusedException => CounterOfferRefusedException::invalidQuantity(),
                static fn (): CounterOfferRefusedException => CounterOfferRefusedException::invalidPrice(),
            ],
            QuoteDraftRefusedException::class => [
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::revisionLocked(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::customerCannotHaveQuotes(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::unknownLine(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::unknownSaleElement(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::noCatalogPrice(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::invalidQuantity(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::invalidPrice(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::invalidDiscount(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::invalidPostage(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::unknownDeliveryModule(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::noteTooLong(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::validityInThePast(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::nothingToSend(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::noLines(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::discountAboveTotal(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::lineUnavailable(),
                static fn (): QuoteDraftRefusedException => QuoteDraftRefusedException::validityNotExtended(),
            ],
        ] as $factories) {
            foreach ($factories as $factory) {
                $refusals[] = $factory();
            }
        }

        return $refusals;
    }

    public function testEveryRefusalHasItsOwnStableReason(): void
    {
        $reasons = array_map(static fn (QuoteException $refusal): string => $refusal->reason(), self::everyRefusal());

        foreach ($reasons as $reason) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $reason);
        }

        // One reason per refusal, but for the ones a buyer is told on a request and on an answer.
        $shared = array_filter(array_count_values($reasons), static fn (int $count): bool => $count > 1);

        self::assertSame(['not_permitted' => 2, 'company_suspended' => 2], $shared);
    }

    public function testEveryMessageIsInTheCatalogsOfTheCore(): void
    {
        foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
            /** @var array<string, string> $catalog */
            $catalog = require \dirname(__DIR__, 4).'/core/lib/Thelia/Config/I18n/'.$locale.'.php';

            foreach (self::everyRefusal() as $refusal) {
                self::assertArrayHasKey($refusal->getMessage(), $catalog, $locale.': '.$refusal->getMessage());
                self::assertNotSame('', $catalog[$refusal->getMessage()]);
            }
        }
    }

    public function testTheMessageNeverCarriesAnIdOrAnAmount(): void
    {
        foreach (self::everyRefusal() as $refusal) {
            self::assertDoesNotMatchRegularExpression('/\d/', $refusal->getMessage(), $refusal->getMessage());
        }
    }
}
