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

use Thelia\Core\Translation\Translator;

/**
 * Class InactiveCouponException.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class InactiveCouponException extends \RuntimeException
{
    public function __construct($couponCode)
    {
        $message = Translator::getInstance()->trans('Coupon code %code is disabled.', ['%code' => $couponCode]);

        parent::__construct($message);
    }
}
