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
 * The provider did not answer, or answered an error. Retry later when $retryable is true (timeout, rate
 * limit or overload of the provider, 5xx); never when it is false (refused key, unknown model, bad request).
 */
#[Exclude]
final class AiProviderUnavailableException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(
        public readonly string $connection,
        public readonly bool $retryable,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct('' !== $message ? $message : \sprintf('The AI connection "%s" is unavailable.', $connection), 0, $previous);
    }
}
