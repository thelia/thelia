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

namespace Thelia\Domain\Ai\Tool\Exception;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Domain\Ai\Exception\AiExceptionInterface;

/**
 * Thrown by a tool when it cannot do what was asked. The message goes back to the model or to the MCP client
 * as it is: write it for them, in English, and never put a secret, a person's data or a stack trace in it.
 */
#[Exclude]
final class AiToolFailedException extends \RuntimeException implements AiExceptionInterface
{
}
