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

use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class CouponNotReleaseException.
 *
 * @author Baixas Alban <abaixas@openstudio.fr>
 */
class CouponNotReleaseException extends \Exception
{
    /**
     * CouponNotReleaseException thrown when a Coupon is not release.
     *
     * @param string $couponCode Coupon code
     */
    public function __construct($couponCode)
    {
        $message = Translator::getInstance()->trans('Coupon %code is not release.', ['%code' => $couponCode]);

        Tlog::getInstance()->addWarning($message);

        parent::__construct($message);
    }
}
