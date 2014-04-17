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
 * Thrown when a Condition receive an invalid Operator
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class InvalidConditionOperatorException extends \RuntimeException
{
    /**
     * InvalidConditionOperatorException thrown when a Condition is given a bad Operator
     *
     * @param string $className Class name
     * @param string $parameter array key parameter
     */
    public function __construct($className, $parameter)
    {
        $message = 'Invalid Operator for Condition ' . $className . ' on parameter ' . $parameter;
        Tlog::getInstance()->addError($message);

        parent::__construct($message);
    }
}
