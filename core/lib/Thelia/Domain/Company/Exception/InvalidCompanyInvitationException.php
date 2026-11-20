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
 * The invitation is not one this shop issued, was altered, has expired, or has been used.
 */
final class InvalidCompanyInvitationException extends CompanyException
{
    public static function notValid(): self
    {
        return new self('This invitation is no longer valid.');
    }

    public static function addressedToSomeoneElse(): self
    {
        return new self('This invitation was sent to another e-mail address: sign in with that account.');
    }
}
