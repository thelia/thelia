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
 * The role of the buyer does not let them place orders for the company.
 */
final class CompanyOrderNotPermittedException extends CompanyOrderRefusedException
{
    public const string VIOLATION_CODE = 'company-order-not-permitted';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Your role in your company does not allow you to place orders.', 0, $previous);
    }

    public function violationCode(): string
    {
        return self::VIOLATION_CODE;
    }
}
