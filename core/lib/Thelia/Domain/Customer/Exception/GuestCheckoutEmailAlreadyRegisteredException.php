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

namespace Thelia\Domain\Customer\Exception;

/**
 * The address a visitor typed at the guest checkout already belongs to a real account.
 *
 * Letting the guest checkout through would hand whoever typed the address an order,
 * an order history entry and a tracking link on someone else's account. The owner of
 * that account signs in instead.
 */
final class GuestCheckoutEmailAlreadyRegisteredException extends \RuntimeException
{
}
