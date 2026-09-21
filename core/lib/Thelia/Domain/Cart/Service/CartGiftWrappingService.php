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

namespace Thelia\Domain\Cart\Service;

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\Exception\GiftMessageTooLongException;
use Thelia\Domain\Checkout\Exception\UnknownGiftWrappingException;
use Thelia\Domain\Checkout\Service\GiftWrappingProvider;
use Thelia\Model\Cart;
use Thelia\Model\GiftWrapping;

/**
 * What the buyer decided about wrapping their order, held on the cart until the order
 * takes a copy of it.
 *
 * The cart keeps the identifier of the wrapping and nothing else — no price, no wording.
 * Everything the buyer is shown is read back off the wrapping row at each display, so a
 * merchant repricing the service before the order is placed charges the new price, and a
 * browser that sends an amount is sending something nobody reads.
 */
final readonly class CartGiftWrappingService
{
    public function __construct(private GiftWrappingProvider $giftWrappingProvider)
    {
    }

    /**
     * Records the wrapping the buyer picked, or clears the choice when given null.
     *
     * At most one per cart: picking a second replaces the first, which is what the
     * exclusive choice at checkout means.
     *
     * @throws UnknownGiftWrappingException when the shop does not offer that wrapping
     * @throws PropelException
     */
    public function chooseGiftWrapping(Cart $cart, ?int $giftWrappingId): void
    {
        if (null === $giftWrappingId) {
            $cart->setGiftWrappingId(null)->save();

            return;
        }

        $giftWrapping = $this->giftWrappingProvider->findActive($giftWrappingId);

        if (!$giftWrapping instanceof GiftWrapping) {
            throw new UnknownGiftWrappingException($giftWrappingId);
        }

        $cart->setGiftWrappingId((int) $giftWrapping->getId())->save();
    }

    /**
     * Records the note for whoever receives the parcel, or clears it.
     *
     * A note of spaces is a note of nothing: it is stored as null rather than as a blank
     * line printed on the delivery note. The length is counted in characters and not in
     * bytes — a note of accented text is not half as long as the same note without.
     *
     * @throws GiftMessageTooLongException when the note exceeds what the shop accepts
     * @throws PropelException
     */
    public function writeGiftMessage(Cart $cart, ?string $giftMessage): void
    {
        $giftMessage = trim((string) $giftMessage);

        if ('' === $giftMessage) {
            $cart->setGiftMessage(null)->save();

            return;
        }

        $length = mb_strlen($giftMessage);

        if ($length > GiftWrapping::MAX_GIFT_MESSAGE_LENGTH) {
            throw new GiftMessageTooLongException(GiftWrapping::MAX_GIFT_MESSAGE_LENGTH, $length);
        }

        $cart->setGiftMessage($giftMessage)->save();
    }

    /**
     * Drops a choice the shop no longer offers.
     *
     * A wrapping turned off between two visits leaves its identifier on the cart, and the
     * foreign key has nothing to say about it since the row still exists. Called before
     * the cart is priced and before the order is placed, so that neither ever charges for
     * a service that is no longer on sale.
     *
     * @throws PropelException
     */
    public function dropGiftWrappingThatIsNoLongerOffered(Cart $cart): void
    {
        $chosenId = $cart->getGiftWrappingId();

        if (null === $chosenId) {
            return;
        }

        if (!$this->giftWrappingProvider->findActive((int) $chosenId) instanceof GiftWrapping) {
            $cart->setGiftWrappingId(null)->save();
        }
    }
}
