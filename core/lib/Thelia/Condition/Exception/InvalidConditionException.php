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

namespace Thelia\Condition\Exception;

/**
 * Thrown when a coupon carries no usable condition.
 *
 * The exception used to log itself at ERROR level. A coupon without a condition is a
 * normal thing for a merchant to create and the checkout handles it by ignoring the
 * coupon, so the line said "failure" on a path that behaves as intended. Whether it is
 * worth a log line, and at which level, belongs to the caller that catches it.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class InvalidConditionException extends \RuntimeException
{
    /**
     * InvalidConditionOperatorException thrown when a Condition is badly implemented.
     *
     * @param string $className Class name
     */
    public function __construct(string $className)
    {
        parent::__construct('Invalid Condition given to '.$className);
    }
}
