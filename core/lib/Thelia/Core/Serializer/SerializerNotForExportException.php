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

namespace Thelia\Core\Serializer;

use Thelia\Exception\UserFacingFailure;

/**
 * An export asked for in a format that is not made for it.
 */
final class SerializerNotForExportException extends \InvalidArgumentException implements UserFacingFailure
{
}
