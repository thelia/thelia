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
 * Class InactiveCouponException
 * @package Thelia\Exception
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class InactiveCouponException extends \RuntimeException
{

    public function __construct($couponCode)
    {
        $message = Translator::getInstance()->trans('Coupon code %code is disabled.', ['%code' => $couponCode ]);

        Tlog::getInstance()->addWarning($message);

        parent::__construct($message);
    }
}
