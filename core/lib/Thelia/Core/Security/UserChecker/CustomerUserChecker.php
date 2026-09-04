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

namespace Thelia\Core\Security\UserChecker;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Thelia\Model\Customer;

/**
 * Keeps a guest row from being signed into.
 *
 * The API login checked the password and nothing else, so a guest row that had just
 * been given one handed out a token straight away. That row is shared by everyone who
 * ever ordered on the address, and nobody proved they own it, so signing in on it means
 * inheriting somebody else's orders for the price of knowing an email. What must be
 * answered first is the activation code, and answering it is exactly what clears
 * `is_guest` — so this one column is the whole check.
 *
 * Reading `enable`, or a pending confirmation token, would not do: `enable` defaults to
 * 0 and a shop that does not confirm addresses leaves it there, and shops carry legacy
 * accounts with a token left over from a registration they never finished. Both would
 * lock out accounts the shop considers open.
 */
final readonly class CustomerUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Customer) {
            return;
        }

        if ($user->isGuest()) {
            throw new CustomUserMessageAccountStatusException('This account is waiting for the activation code sent to its email address.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
