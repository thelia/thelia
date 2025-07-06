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

namespace Thelia\Exception;

use Thelia\Log\Tlog;

/**
 * Thrown when a Condition receives an invalid Parameter.
 *
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 */
class InvalidConditionValueException extends \RuntimeException
{
    /**
     * InvalidConditionValueException thrown when a Condition is given a bad Parameter.
     *
     * @param string $className Class name
     * @param string $parameter array key parameter
     */
    public function __construct($className, $parameter)
    {
        $message = 'Invalid Parameter for Condition '.$className.' on parameter '.$parameter;
        Tlog::getInstance()->addError($message);

        parent::__construct($message);
    }
}
