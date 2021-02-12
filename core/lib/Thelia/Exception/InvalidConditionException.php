<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Exception;

use Thelia\Log\Tlog;

/**
 * Thrown when a Condition is badly implemented.
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
    public function __construct($className)
    {
        $message = 'Invalid Condition given to '.$className;
        Tlog::getInstance()->addError($message);

        parent::__construct($message);
    }
}
