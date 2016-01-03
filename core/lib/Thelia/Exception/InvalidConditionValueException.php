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
 * Thrown when a Condition receives an invalid Parameter
 *
 * @package Condition
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class InvalidConditionValueException extends \RuntimeException
{
    /**
     * InvalidConditionValueException thrown when a Condition is given a bad Parameter
     *
     * @param string $className Class name
     * @param string $parameter array key parameter
     */
    public function __construct($className, $parameter)
    {
        $message = 'Invalid Parameter for Condition ' . $className . ' on parameter ' . $parameter;
        Tlog::getInstance()->addError($message);

        parent::__construct($message);
    }
}
