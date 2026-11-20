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
 * An invitation is not sent: the address already belongs to a buyer, or too many were sent already.
 */
final class CompanyInvitationRefusedException extends CompanyException
{
    public static function alreadyInACompany(): self
    {
        return new self('This e-mail address already belongs to a buyer of a company.');
    }

    public static function tooManySent(): self
    {
        return new self('Too many invitations were sent: try again later.');
    }
}
