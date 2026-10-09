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

namespace Thelia\Domain\Payment\Exception;

/**
 * The payment module of the order cannot give money back through its provider: the refund is
 * recorded by hand instead.
 */
final class RefundNotSupportedException extends PaymentException
{
    public function __construct(string $orderRef, ?string $moduleCode)
    {
        parent::__construct(\sprintf('Order %s: the payment module %s cannot refund through its provider. Record the refund made outside it instead.', $orderRef, $moduleCode ?? '(none)'));
    }
}
