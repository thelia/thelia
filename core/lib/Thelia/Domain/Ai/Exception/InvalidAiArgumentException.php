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
 * A value object refused what it was built with: a bug in the calling code, raised before any call.
 */
#[Exclude]
final class InvalidAiArgumentException extends \InvalidArgumentException implements AiExceptionInterface
{
}
