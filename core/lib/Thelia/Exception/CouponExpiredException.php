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
 * Thrown when an Expired Coupon is tried
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponExpiredException extends \Exception
{
    /**
     * CouponExpiredException thrown when a Coupon is expired
     *
     * @param string $couponCode Coupon code
     */
    public function __construct($couponCode)
    {
        $message = 'Expired Coupon ' . $couponCode . 'attempt';
        Tlog::getInstance()->addWarning($message);

        parent::__construct($message);
    }
}
