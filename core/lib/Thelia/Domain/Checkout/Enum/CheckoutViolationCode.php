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
 * What a client branches on when the checkout refuses a cart.
 *
 * The message of a refusal is written for the buyer: it is translated, it gets reworded,
 * and a shop may replace it altogether. None of that may break a client that has to tell
 * "no address yet" from "this payment module will not take this cart", so every refusal
 * carries one of these next to its sentence. The values are part of the published
 * contract of the front API: they are kebab-case, they never change, and a new family
 * gets a new case rather than a new meaning for an old one.
 *
 * A module raising its own refusal answers its own string — prefer prefixing it with the
 * module code — and CheckoutRefused is what a refusal that says nothing more comes back
 * as.
 */
enum CheckoutViolationCode: string
{
    case CartEmpty = 'cart-empty';

    case AddressMissing = 'address-missing';

    case DeliveryInvalid = 'delivery-invalid';

    case InvoiceAddressIncomplete = 'invoice-address-incomplete';

    case PaymentInvalid = 'payment-invalid';

    case ConsentMissing = 'consent-missing';

    /**
     * An answer was given to a consent this shop is not asking for. It is a fault of the
     * client rather than something the buyer can settle, and it is reported in the same
     * shape as the rest so that one payload answers every refused placement.
     */
    case ConsentUnknown = 'consent-unknown';

    /**
     * A gift wrapping was asked for that this shop does not offer — turned off, deleted,
     * or never its own. Refused rather than ignored, for the reason ConsentUnknown is: a
     * caller working from a stale list would otherwise get an order without the service
     * it thinks it bought.
     */
    case GiftWrappingUnknown = 'gift-wrapping-unknown';

    /**
     * The note for the recipient is longer than the shop accepts. Refused whole: cutting
     * it would print half a sentence on the parcel.
     */
    case GiftMessageTooLong = 'gift-message-too-long';

    case GuestCheckoutNotAllowed = 'guest-checkout-not-allowed';

    /**
     * A refusal of the checkout that names no family of its own — a module's step, or a
     * core exception a later version has not given a code to yet.
     */
    case CheckoutRefused = 'checkout-refused';
}
