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

final class DeferredCaptureNotSupportedException extends PaymentException
{
    public function __construct(string $orderRef, ?string $moduleCode)
    {
        parent::__construct(\sprintf(
            'Order %s: its payment module%s does not separate the authorization from the capture, there is nothing to capture by hand.',
            $orderRef,
            null === $moduleCode ? '' : ' '.$moduleCode,
        ));
    }
}
