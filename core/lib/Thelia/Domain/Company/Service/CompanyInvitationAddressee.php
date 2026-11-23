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

namespace Thelia\Domain\Company\Service;

/**
 * How an address an invitation was sent to is kept once the invitation is spent: by its digest,
 * so that a later invitation to the same address is recognised whatever became of the account
 * that held it, without the address itself being kept.
 */
final class CompanyInvitationAddressee
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function digest(string $email): string
    {
        return hash('sha256', self::normalize($email));
    }
}
