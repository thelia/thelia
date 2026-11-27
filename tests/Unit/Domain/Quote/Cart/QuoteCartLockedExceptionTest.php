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

namespace Thelia\Tests\Unit\Domain\Quote\Cart;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\Cart\QuoteCartLockedException;

final class QuoteCartLockedExceptionTest extends TestCase
{
    public function testEachRefusalHasItsOwnReasonAndAMessageTheCatalogsTranslate(): void
    {
        $refusals = [QuoteCartLockedException::linesAreFrozen(), QuoteCartLockedException::couponIsRefused()];

        self::assertSame(['quote_cart_locked', 'quote_cart_coupon'], array_map(static fn (QuoteCartLockedException $refusal): string => $refusal->reason(), $refusals));

        foreach (['en_US', 'fr_FR', 'de_DE'] as $locale) {
            /** @var array<string, string> $catalog */
            $catalog = require \dirname(__DIR__, 5).'/core/lib/Thelia/Config/I18n/'.$locale.'.php';

            foreach ([...$refusals, 'This shop only sells through quotes: ask for a quote for this cart.'] as $refusal) {
                $message = $refusal instanceof \Throwable ? $refusal->getMessage() : $refusal;

                self::assertArrayHasKey($message, $catalog, $locale.': '.$message);
                self::assertNotSame('', $catalog[$message]);
            }
        }
    }
}
