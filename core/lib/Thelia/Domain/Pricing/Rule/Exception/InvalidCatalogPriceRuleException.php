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

namespace Thelia\Domain\Pricing\Rule\Exception;

/**
 * A rule definition the shop refuses to store. The message is meant for the
 * merchant who typed it.
 */
class InvalidCatalogPriceRuleException extends \InvalidArgumentException
{
}
