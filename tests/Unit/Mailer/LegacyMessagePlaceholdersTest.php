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
            'Shop {config key="store_name" default="Thelia"}',
        ] as $source) {
            $this->assertSame($source, LegacyMessagePlaceholders::interpolate($source));
        }
    }

    public function testItRewritesTheConfigurationTagOfAThelia2Subject(): void
    {
        $this->assertSame(
            'Votre commande {{ order_ref }} chez {{ config(\'store_name\') }}',
            LegacyMessagePlaceholders::interpolate('Votre commande {$order_ref} chez {config key="store_name"}'),
        );
    }

    public function testItRewritesTheConfigurationTagWhateverTheQuotesAndTheSpacingSmartyAccepts(): void
    {
        foreach ([
            "Shop {config key='store_name'}",
            'Shop {config  key = "store_name" }',
        ] as $source) {
            $this->assertSame("Shop {{ config('store_name') }}", LegacyMessagePlaceholders::interpolate($source));
        }
    }

    public function testItKeepsAQuoteOfAConfigurationKeyInsideTheTwigString(): void
    {
        $this->assertSame(
            "Shop {{ config('shop\\'s_name') }}",
            LegacyMessagePlaceholders::interpolate('Shop {config key="shop\'s_name"}'),
        );
    }

    public function testItLeavesEveryConfigurationTagItDoesNotFullyUnderstandAlone(): void
    {
        foreach ([
            // The Spanish seed of Thelia 2.1 translated the tag name itself.
            'Nueva orden {$order_ref} ubicada en {tecla config = "store_name"}',
            // Smarty reads a space after the opening brace as literal text.
            'Shop { config key="store_name"}',
            // A double-quoted key Smarty would interpolate, and an escaped quote.
            'Shop {config key="$store_key"}',
            "Shop {config key='shop\\'s_name'}",
        ] as $source) {
            $this->assertSame(
                str_replace('{$order_ref}', '{{ order_ref }}', $source),
                LegacyMessagePlaceholders::interpolate($source),
            );
        }
    }

    public function testItLeavesTheConfigurationTagOfAMessageAlreadyWrittenInTwigAlone(): void
    {
        $this->assertSame(
            '{{ config(\'store_name\') }}: order {{ order_ref }} at {config key="store_name"}',
            LegacyMessagePlaceholders::interpolate('{{ config(\'store_name\') }}: order {{ order_ref }} at {config key="store_name"}'),
        );
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
