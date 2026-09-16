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

namespace Thelia\Tests\Unit\Mailer;

use PHPUnit\Framework\TestCase;
use Thelia\Mailer\LegacyMessagePlaceholders;

final class LegacyMessagePlaceholdersTest extends TestCase
{
    public function testItRewritesASingleSmartyVariable(): void
    {
        $this->assertSame(
            'Payment of order {{ order_ref }}',
            LegacyMessagePlaceholders::interpolate('Payment of order {$order_ref}'),
        );
    }

    public function testItRewritesEverySmartyVariableOfTheString(): void
    {
        $this->assertSame(
            'Order {{ order_ref }} of {{ customer_name }}',
            LegacyMessagePlaceholders::interpolate('Order {$order_ref} of {$customer_name}'),
        );
    }

    public function testItLeavesAMessageAlreadyWrittenInTwigAlone(): void
    {
        $this->assertSame(
            'Order {{ order_ref }}, paid by {$customer}',
            LegacyMessagePlaceholders::interpolate('Order {{ order_ref }}, paid by {$customer}'),
        );
    }

    public function testItLeavesEverySmartyConstructThatIsNotAPlainVariableAlone(): void
    {
        foreach ([
            'Order {$order_ref|upper}',
            'Order {$order.ref}',
            '{if $order}Order{/if}',
            'Shop {config key="store_name"}',
        ] as $source) {
            $this->assertSame($source, LegacyMessagePlaceholders::interpolate($source));
        }
    }

    public function testItLeavesAMessageWithoutAPlaceholderAlone(): void
    {
        $this->assertSame(
            'Your order has been shipped',
            LegacyMessagePlaceholders::interpolate('Your order has been shipped'),
        );
    }

    public function testItAcceptsAMessageThatWasNeverWritten(): void
    {
        $this->assertSame('', LegacyMessagePlaceholders::interpolate(null));
    }
}
