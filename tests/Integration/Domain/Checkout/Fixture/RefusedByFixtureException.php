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

namespace Thelia\Tests\Integration\Domain\Checkout\Fixture;

use Thelia\Domain\Checkout\Exception\CheckoutException;

/**
 * The refusal of the fixture step, given a type of its own so that no core guard can be
 * mistaken for it in an assertion.
 */
final class RefusedByFixtureException extends CheckoutException
{
}
