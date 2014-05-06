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
 * Thrown when the Facade is not set
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class MissingFacadeException extends \RuntimeException
{
    /**
     * {@inheritdoc}
     */
    public function __construct($message, $code = null, $previous = null)
    {
        Tlog::getInstance()->addError($message);

        parent::__construct($message, $code, $previous);
    }
}
