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

use Thelia\Module\PaymentModuleWithStoredMethodsInterface;

/**
 * The module does not implement PaymentModuleWithStoredMethodsInterface, or says it
 * does not offer stored methods at the moment.
 */
final class StoredMethodsNotSupportedException extends CustomerPaymentMethodException
{
    public function __construct(public readonly string $moduleCode)
    {
        parent::__construct(\sprintf(
            'The module "%s" does not offer stored payment methods (see %s).',
            $moduleCode,
            PaymentModuleWithStoredMethodsInterface::class,
        ));
    }
}
