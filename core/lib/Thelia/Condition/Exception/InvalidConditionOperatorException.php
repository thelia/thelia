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

use Thelia\Log\Tlog;

/**
 * Thrown when a Condition receive an invalid Operator.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class InvalidConditionOperatorException extends \RuntimeException
{
    /**
     * InvalidConditionOperatorException thrown when a Condition is given a bad Operator.
     *
     * @param string $className Class name
     * @param string $parameter array key parameter
     */
    public function __construct(string $className, string $parameter)
    {
        $message = 'Invalid Operator for Condition '.$className.' on parameter '.$parameter;
        Tlog::getInstance()->addError($message);

        parent::__construct($message);
    }
}
