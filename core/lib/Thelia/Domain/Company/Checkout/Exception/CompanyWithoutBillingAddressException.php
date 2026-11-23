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

namespace Thelia\Domain\Company\Checkout\Exception;

/**
 * The company has not chosen its billing address yet: the invoice of its orders could not carry its
 * address and its legal identifiers, so its buyers place nothing until it has.
 */
final class CompanyWithoutBillingAddressException extends CompanyOrderRefusedException
{
    public const string VIOLATION_CODE = 'company-without-billing-address';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Your company has no billing address yet: orders can be placed once it is chosen.', 0, $previous);
    }

    public function violationCode(): string
    {
        return self::VIOLATION_CODE;
    }
}
