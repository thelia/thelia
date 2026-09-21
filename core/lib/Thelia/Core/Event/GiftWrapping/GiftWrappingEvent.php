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

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\GiftWrapping;

class GiftWrappingEvent extends ActionEvent
{
    public function __construct(protected ?GiftWrapping $giftWrapping = null)
    {
    }

    public function hasGiftWrapping(): bool
    {
        return $this->giftWrapping instanceof GiftWrapping;
    }

    public function getGiftWrapping(): ?GiftWrapping
    {
        return $this->giftWrapping;
    }

    public function setGiftWrapping(GiftWrapping $giftWrapping): static
    {
        $this->giftWrapping = $giftWrapping;

        return $this;
    }
}
