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

namespace Thelia\Domain\Checkout\DTO;

use Thelia\Model\Cart;
use Thelia\Module\ExpressPaymentModuleInterface;

/**
 * A request the shop has agreed to read: the cart of the session, and the express module
 * whose token came with it.
 */
final readonly class ExpressCheckoutAccess
{
    public function __construct(
        public Cart $cart,
        public int $paymentModuleId,
        public ExpressPaymentModuleInterface $module,
    ) {
    }
}
