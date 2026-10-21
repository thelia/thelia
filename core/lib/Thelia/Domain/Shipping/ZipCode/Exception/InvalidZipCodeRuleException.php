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

namespace Thelia\Domain\Shipping\ZipCode\Exception;

/**
 * A postal code rule refused for what the merchant wrote: a pattern that can match nothing,
 * a country the zone does not hold, a zone that does not exist. Its message is a sentence a
 * back office shows as is; any other InvalidArgumentException is not.
 */
final class InvalidZipCodeRuleException extends \InvalidArgumentException
{
}
