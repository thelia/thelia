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

namespace Thelia\Tests\Unit\Domain\Quote\Acceptance;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Quote\Acceptance\QuotePlacement;
use Thelia\Domain\Quote\Cart\QuoteOrderSlot;
use Thelia\Domain\Quote\Exception\QuoteAlreadyOrderedException;
use Thelia\Domain\Quote\Exception\QuoteCartChangedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;

/**
 * What a buyer is told when a quote cannot be accepted or ordered: a reason that does not depend on
 * the language, and a message the catalogs of the core translate.
 */
final class QuoteAcceptanceExceptionsTest extends TestCase
{
    /**
     * @return array<string, array{QuoteException, string}>
     */
    public static function refusals(): array
    {
        return [
            'already ordered' => [new QuoteAlreadyOrderedException(), 'quote_already_ordered'],
            'lines unavailable' => [new QuoteLinesUnavailableException([['reference' => 'REF1', 'title' => 'Title']]), 'quote_lines_unavailable'],
            'currency unavailable' => [new QuoteCurrencyUnavailableException(), 'quote_currency_unavailable'],
            'cart changed' => [new QuoteCartChangedException(), 'quote_cart_changed'],
        ];
    }

    #[DataProvider('refusals')]
    public function testEveryRefusalHasItsStableReason(QuoteException $refusal, string $reason): void
    {
        self::assertSame($reason, $refusal->reason());
    }

    #[DataProvider('refusals')]
    public function testEveryMessageIsInTheCatalogsOfTheCoreAndNamesNoAmountNorId(QuoteException $refusal): void
    {
        self::assertDoesNotMatchRegularExpression('/\d/', $refusal->getMessage());

        foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
            /** @var array<string, string> $catalog */
            $catalog = require \dirname(__DIR__, 5).'/core/lib/Thelia/Config/I18n/'.$locale.'.php';

            self::assertArrayHasKey($refusal->getMessage(), $catalog, $locale);
            self::assertNotSame('', $catalog[$refusal->getMessage()]);
        }
    }

    public function testTheLinesThatStandInTheWayAreCarried(): void
    {
        $lines = [['reference' => 'REF1', 'title' => 'First'], ['reference' => 'REF2', 'title' => 'Second']];

        self::assertSame($lines, (new QuoteLinesUnavailableException($lines))->lines());
    }

    public function testTheSecondOrderIsRecognisedWhateverTheWordingOfTheDatabase(): void
    {
        $placement = new QuotePlacement(new QuoteOrderSlot());

        foreach ([
            'MariaDB' => "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '12' for key 'uk_order_quote_id'",
            'MySQL 8' => "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '12' for key 'order.uk_order_quote_id'",
        ] as $database => $message) {
            $driver = new \PDOException($message);
            $driver->errorInfo = ['23000', 1062, $message];
            $failure = new PropelException('Unable to execute INSERT statement [INSERT INTO `order` ...]', 0, $driver);

            $refusal = $placement->refusalOf($failure);

            self::assertInstanceOf(QuoteAlreadyOrderedException::class, $refusal, $database);
            self::assertSame($failure, $refusal->getPrevious());
        }
    }

    public function testAnotherFailureIsNotTakenForASecondOrder(): void
    {
        $placement = new QuotePlacement(new QuoteOrderSlot());

        $otherKey = new \PDOException("Duplicate entry 'REF' for key 'order.ref_UNIQUE'");
        $otherKey->errorInfo = ['23000', 1062, "Duplicate entry 'REF' for key 'order.ref_UNIQUE'"];
        $otherError = new \PDOException('Deadlock found when trying to get lock; uk_order_quote_id');
        $otherError->errorInfo = ['40001', 1213, 'Deadlock found'];

        self::assertNull($placement->refusalOf(new PropelException('x', 0, $otherKey)));
        self::assertNull($placement->refusalOf(new PropelException('x', 0, $otherError)));
        self::assertNull($placement->refusalOf(new \RuntimeException('uk_order_quote_id')));
    }
}
