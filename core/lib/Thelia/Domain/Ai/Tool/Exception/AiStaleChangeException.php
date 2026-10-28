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
 * Thrown by AiWriteToolInterface::apply() when the current state of the shop no longer matches the `before`
 * of the approved change: someone changed it after the proposal. The approval ends as `failed`.
 */
#[Exclude]
final class AiStaleChangeException extends \RuntimeException implements AiExceptionInterface
{
}
