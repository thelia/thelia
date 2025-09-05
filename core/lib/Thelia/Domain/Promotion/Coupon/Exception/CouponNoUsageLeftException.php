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

namespace Thelia\Domain\Promotion\Coupon\Exception;

use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Thrown when a Coupon with no usage lect (etiher overall or per customer usage) is tried.
 *
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CouponNoUsageLeftException extends \Exception
{
    /**
     * CouponExpiredException thrown when a Coupon is expired.
     *
     * @param string $couponCode Coupon code
     */
    public function __construct(string $couponCode)
    {
        $message = Translator::getInstance()->trans('Maximum usage count reached for coupon %code', ['%code' => $couponCode]);

        Tlog::getInstance()->addWarning($message);

        parent::__construct($message);
    }
}
