<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Exception;

use Thelia\Log\Tlog;

/**
 * Thrown when a Condition is badly implemented
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class InvalidConditionException extends \RuntimeException
{
    /**
     * InvalidConditionOperatorException thrown when a Condition is badly implemented
     *
     * @param string $className Class name
     */
    public function __construct($className)
    {
        $message = 'Invalid Condition given to ' . $className;
        Tlog::getInstance()->addError($message);

        parent::__construct($message);
    }
}
