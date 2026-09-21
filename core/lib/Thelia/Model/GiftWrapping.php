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

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Base\GiftWrapping as BaseGiftWrapping;
use Thelia\Model\Tools\PositionManagementTrait;

/**
 * A service the shop invoices beside its goods: a gift wrapping the buyer picks at
 * checkout, priced and taxed the way a product is.
 *
 * A price of zero is a wrapping offered, not a wrapping without a price: that is why it
 * is a column of its own, read as a number, and never a configuration entry — those
 * read '0' as "not set".
 *
 * Turning a wrapping off stops it being offered and leaves every order that already
 * carries it readable, since what those orders show is the wording frozen on their own
 * line, not this row.
 */
class GiftWrapping extends BaseGiftWrapping
{
    use PositionManagementTrait;

    /**
     * How long the note to the recipient may be.
     *
     * Bounded because it is typed by a visitor and ends up on a printed document: a note
     * longer than this is refused by the server with a message saying so, and never
     * silently cut.
     */
    public const MAX_GIFT_MESSAGE_LENGTH = 500;

    public function isActive(): bool
    {
        return 1 === $this->getActive();
    }

    /**
     * A wrapping the shop does not charge for. Read on the price alone: a merchant
     * offering the service sets it to zero, and nothing else marks it.
     */
    public function isFree(): bool
    {
        return 0.0 === round((float) $this->getPrice(), 6);
    }

    public function preInsert(?ConnectionInterface $con = null): bool
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }
}
