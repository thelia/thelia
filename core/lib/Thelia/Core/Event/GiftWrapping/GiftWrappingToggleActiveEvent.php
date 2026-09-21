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

namespace Thelia\Core\Event\GiftWrapping;

/**
 * Turns a gift wrapping on or off, which is how a shop stops offering one without
 * touching the orders already placed with it.
 */
class GiftWrappingToggleActiveEvent extends GiftWrappingEvent
{
    protected int $giftWrappingId;

    public function __construct(int $giftWrappingId)
    {
        parent::__construct();

        $this->giftWrappingId = $giftWrappingId;
    }

    public function getGiftWrappingId(): int
    {
        return $this->giftWrappingId;
    }

    public function setGiftWrappingId(int $giftWrappingId): static
    {
        $this->giftWrappingId = $giftWrappingId;

        return $this;
    }
}
