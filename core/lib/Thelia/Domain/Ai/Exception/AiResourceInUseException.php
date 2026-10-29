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

namespace Thelia\Domain\Ai\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A setting cannot be deleted because something still relies on it (a connection or a model a range uses). The
 * message says what to do first.
 */
#[Exclude]
final class AiResourceInUseException extends \DomainException implements AiExceptionInterface
{
}
