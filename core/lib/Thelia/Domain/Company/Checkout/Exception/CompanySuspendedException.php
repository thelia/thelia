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
 * The company is suspended: its buyers stay signed in and read their history, and place nothing.
 */
final class CompanySuspendedException extends CompanyOrderRefusedException
{
    public const string VIOLATION_CODE = 'company-suspended';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Your company account is suspended: orders cannot be placed until it is reactivated.', 0, $previous);
    }

    public function violationCode(): string
    {
        return self::VIOLATION_CODE;
    }
}
