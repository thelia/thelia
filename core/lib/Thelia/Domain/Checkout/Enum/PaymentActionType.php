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

namespace Thelia\Domain\Checkout\Enum;

/**
 * What is left for the front to do once the order is placed.
 *
 * A payment module answers the core with an HTTP Response, which is the right thing to
 * hand a browser and the wrong thing to hand a client that is not one. These three cases
 * are everything the shipped modules and the ones written against them produce, and the
 * values are part of the published contract of the front API.
 */
enum PaymentActionType: string
{
    /**
     * Nothing to do: the payment was settled on the spot, or it happens off the web
     * altogether — a cheque is posted, a transfer is made at a bank.
     */
    case None = 'none';

    /** Send the buyer to the gateway, at the URL the action carries. */
    case Redirect = 'redirect';

    /**
     * Render the markup the action carries. A gateway that will not take a plain
     * redirection is handed a form the browser posts to it, and only the module knows
     * what goes in it.
     */
    case Form = 'form';
}
