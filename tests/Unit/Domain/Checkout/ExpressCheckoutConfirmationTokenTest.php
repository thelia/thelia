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

namespace Thelia\Tests\Unit\Domain\Checkout;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Checkout\Service\ExpressCheckoutConfirmationToken;
use Thelia\Model\Cart;

/**
 * The token that comes with each express button is worth something for one cart, one
 * module and one shop: anything else is a page that was showing something else.
 */
final class ExpressCheckoutConfirmationTokenTest extends TestCase
{
    public function testATokenIsAcceptedForTheCartAndTheModuleItWasIssuedFor(): void
    {
        $tokens = new ExpressCheckoutConfirmationToken('secret');

        self::assertTrue($tokens->isIssuedFor($tokens->issueFor($this->cart(12), 7), $this->cart(12), 7));
    }

    public function testATokenIsRefusedForAnotherCart(): void
    {
        $tokens = new ExpressCheckoutConfirmationToken('secret');

        self::assertFalse($tokens->isIssuedFor($tokens->issueFor($this->cart(12), 7), $this->cart(13), 7));
    }

    public function testATokenIsRefusedForAnotherModule(): void
    {
        $tokens = new ExpressCheckoutConfirmationToken('secret');

        self::assertFalse($tokens->isIssuedFor($tokens->issueFor($this->cart(12), 7), $this->cart(12), 8));
    }

    /** Another shop, or the same one after its secret changed: a token it never signed. */
    public function testATokenSignedUnderAnotherSecretIsRefused(): void
    {
        $token = (new ExpressCheckoutConfirmationToken('another secret'))->issueFor($this->cart(12), 7);

        self::assertFalse((new ExpressCheckoutConfirmationToken('secret'))->isIssuedFor($token, $this->cart(12), 7));
    }

    /** "1" then "27" and "12" then "7" must not sign the same thing. */
    public function testTheCartAndTheModuleCannotBeShiftedIntoEachOther(): void
    {
        $tokens = new ExpressCheckoutConfirmationToken('secret');

        self::assertFalse($tokens->isIssuedFor($tokens->issueFor($this->cart(1), 27), $this->cart(12), 7));
    }

    public function testAnEmptyTokenIsRefused(): void
    {
        self::assertFalse((new ExpressCheckoutConfirmationToken('secret'))->isIssuedFor('', $this->cart(12), 7));
    }

    private function cart(int $id): Cart
    {
        return (new Cart())->setId($id);
    }
}
