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
 * The actor sent more requests than the rate of the usage allows. Tell the person to wait until $retryAfter.
 */
#[Exclude]
final class AiRateLimitExceededException extends \RuntimeException implements AiExceptionInterface
{
    public function __construct(public readonly string $usageId, public readonly ?\DateTimeImmutable $retryAfter = null)
    {
        parent::__construct(\sprintf('Too many requests for the AI usage "%s".', $usageId));
    }
}
