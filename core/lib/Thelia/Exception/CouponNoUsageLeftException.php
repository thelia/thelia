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

use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Thrown when a Coupon with no usage lect (etiher overall or per customer usage) is tried
 *
 * @package Coupon
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CouponNoUsageLeftException extends \Exception
{
    /**
     * CouponExpiredException thrown when a Coupon is expired
     *
     * @param string $couponCode Coupon code
     */
    public function __construct($couponCode)
    {
        $message = Translator::getInstance()->trans('Maximum usage count reached for coupon %code', ['%code' => $couponCode ]);

        Tlog::getInstance()->addWarning($message);

        parent::__construct($message);
    }
}
