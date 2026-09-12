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

use Thelia\Model\Base\OrderReturnStatus as BaseOrderReturnStatus;
use Thelia\Model\Tools\PositionManagementTrait;

class OrderReturnStatus extends BaseOrderReturnStatus
{
    use PositionManagementTrait;

    public const CODE_REQUESTED = 'requested';
    public const CODE_INFO_AWAITED = 'info_awaited';
    public const CODE_ACCEPTED = 'accepted';
    public const CODE_REFUSED = 'refused';
    public const CODE_RECEIVED = 'received';
    public const CODE_SETTLED = 'settled';
    public const CODE_EXPIRED = 'expired';

    /**
     * The canonical status codes, the only values a custom status may declare as its equivalence.
     */
    public const CANONICAL_CODES = [
        self::CODE_REQUESTED,
        self::CODE_INFO_AWAITED,
        self::CODE_ACCEPTED,
        self::CODE_REFUSED,
        self::CODE_RECEIVED,
        self::CODE_SETTLED,
        self::CODE_EXPIRED,
    ];

    /**
     * The canonical code this status stands for.
     *
     * A protected (native) status always stands for its own code. A custom status stands for the
     * canonical code it declares as its equivalence, or for its own code when it declares none.
     */
    public function getEffectiveCode(): string
    {
        if ($this->getProtectedStatus()) {
            return $this->getCode();
        }

        return $this->getEquivalentCode() ?: $this->getCode();
    }

    /**
     * Check if the current status is $statusCode or, when $statusCode is an array, if the current
     * status is one of them. The comparison is made on the effective code, so a custom status
     * declaring an equivalence answers for the canonical code it stands for.
     *
     * @param string|array $statusCode one or several of the OrderReturnStatus::CODE_xxx constants
     */
    public function hasStatusHelper(string|array $statusCode): bool
    {
        $effectiveCode = $this->getEffectiveCode();

        if (\is_array($statusCode)) {
            return \in_array($effectiveCode, $statusCode, true);
        }

        return $effectiveCode === $statusCode;
    }
}
