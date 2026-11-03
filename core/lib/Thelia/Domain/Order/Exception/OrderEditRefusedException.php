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

use Thelia\Domain\Order\Enum\OrderEditRefusal;

final class OrderEditRefusedException extends OrderException
{
    public const EDIT_REFUSED = 310;

    public function __construct(
        public readonly OrderEditRefusal $refusal,
        string $detail = '',
    ) {
        parent::__construct(
            \sprintf('Order edit refused (%s)%s', $refusal->value, '' === $detail ? '' : ': '.$detail),
            self::EDIT_REFUSED,
        );
    }
}
