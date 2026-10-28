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
 * An approval could not be decided; `reason` says why. The message is safe to show to the administrator.
 */
#[Exclude]
final class AiToolApprovalException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(public readonly AiToolApprovalFailure $reason, string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct('' === $message ? \sprintf('The approval cannot be decided (%s).', $reason->value) : $message, 0, $previous);
    }
}
