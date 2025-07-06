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

use RuntimeException;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Class InactiveCouponException.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class InactiveCouponException extends RuntimeException
{
    public function __construct($couponCode)
    {
        $message = Translator::getInstance()->trans('Coupon code %code is disabled.', ['%code' => $couponCode]);

        Tlog::getInstance()->addWarning($message);

        parent::__construct($message);
    }
}
