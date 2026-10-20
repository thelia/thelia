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

namespace Thelia\Domain\Checkout\Exception;

/**
 * The express payment stopped before creating anything.
 *
 * Every one of these means the same thing to a buyer (the wallet did not go through, the
 * other payment methods are still there) and something different to whoever reads the
 * logs.
 */
final class ExpressCheckoutRefusedException extends \RuntimeException
{
    public static function becauseTheTotalDoesNotMatch(float $shownToTheBuyer, float $computed): self
    {
        return new self(\sprintf(
            'The wallet confirmed %.2f while the shop computes %.2f: no order is created for an amount the buyer never saw.',
            $shownToTheBuyer,
            $computed
        ));
    }

    /**
     * Identifying the buyer belongs to the checkout, which binds the cart to them: a service
     * that bound it itself, to a buyer the session does not know, would have the cart
     * duplicated and deleted under it.
     */
    public static function becauseTheCartCarriesNoCustomer(): self
    {
        return new self('This cart belongs to nobody: the buyer is identified before the express placement, not by it.');
    }

    public static function becauseThereIsNoCartToPayFor(): self
    {
        return new self('There is no cart in this session to pay for.');
    }

    /**
     * The wallet takes the carrier and the addresses of the cart, and this cart has not been
     * given them yet.
     */
    public static function becauseNoDeliveryIsChosenYet(): self
    {
        return new self('Choose a delivery method before paying.');
    }

    /**
     * The checkout identifies the buyer before it shows a wallet's button; a session that
     * holds nobody did not come through it.
     */
    public static function becauseTheBuyerIsNotIdentified(): self
    {
        return new self('The buyer has to be identified before paying from the checkout.');
    }
}
