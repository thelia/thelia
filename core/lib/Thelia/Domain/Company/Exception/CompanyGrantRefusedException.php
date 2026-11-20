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

namespace Thelia\Domain\Company\Exception;

/**
 * A buyer of a company may not do what they were asked: give a role or limits that go beyond their
 * own, change or detach a colleague whose role does, or use a right to manage buyers or to edit the
 * company they do not hold.
 */
final class CompanyGrantRefusedException extends CompanyException
{
    public static function issuerCannotManageBuyers(): self
    {
        return new self('The issuer may not manage the buyers of this company.');
    }

    public static function issuerCannotEditTheCompany(): self
    {
        return new self('Only a buyer whose role edits the company may change its details.');
    }

    public static function roleGoesBeyondTheIssuer(): self
    {
        return new self('The role grants permissions the issuer does not hold.');
    }

    public static function limitsGoBeyondTheIssuer(): self
    {
        return new self('The limits are higher than the issuer\'s own.');
    }
}
