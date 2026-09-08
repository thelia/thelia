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

namespace Thelia\Tests\Integration\Domain\Checkout;

use Thelia\Core\Translation\Translator;
use Thelia\Test\IntegrationTestCase;

/**
 * The sentences a buyer reads when the checkout turns their order away.
 *
 * They are the last thing seen at the most sensitive step of the tunnel, and they sit
 * next to a page that is otherwise in the buyer's own language: an untranslated one
 * reads as a bug.
 */
final class CheckoutRefusalWordingTest extends IntegrationTestCase
{
    public function testTheIncompleteBillingAddressRefusalIsTranslated(): void
    {
        self::assertSame(
            "L'adresse de facturation ne comporte pas toutes les informations qu'une facture exige",
            Translator::getInstance()->trans(
                'The billing address is missing information an invoice requires',
                [],
                'core',
                'fr_FR',
            ),
        );
    }
}
