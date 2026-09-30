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

namespace Thelia\Module\Exception;

/**
 * A module.xml declares something the shop refuses to act on: a value it cannot read, an
 * element the module schema rejects. The install entry points catch it to stop with a
 * readable message instead of a trace.
 */
final class InvalidModuleDescriptorException extends \InvalidArgumentException
{
}
