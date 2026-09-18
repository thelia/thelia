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

namespace Thelia\Domain\Order\Exception;

use Thelia\Exception\TheliaProcessException;

/**
 * The shop no longer holds what the cart asks for.
 *
 * A family of its own inside TheliaProcessException, which is also what a missing
 * customer id, a module answering nothing and every other wiring failure of an order is
 * raised as. A caller has to tell the two apart: a shortage is a conflict with the state
 * of the shop, and the same request a minute earlier would have gone through; the rest is
 * a defect, its sentence is written for a log, and telling a buyer to try again is
 * telling them to wait for something that will not happen. Matching on the wording of the
 * message was the only way to do that, and a reworded message silently turned every
 * defect into "please retry".
 *
 * The product is named because a refusal has to say which line to change: "not enough
 * stock" about a cart of ten lines is not something a buyer can act on. The reference is
 * the one printed on the catalogue page they came from, so it is theirs to read.
 */
final class StockShortageException extends TheliaProcessException
{
    public function __construct(
        public readonly ?string $productReference = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(self::messageFor($productReference), previous: $previous);
    }

    public static function messageFor(?string $productReference): string
    {
        return \sprintf('Not enough stock for product %s', $productReference ?? '');
    }
}
